#!/usr/bin/python3

import os
import re
import sys
import math
import random
import requests
import time
import argparse
from termcolor import colored
from texttable import Texttable
from multiprocessing.dummy import Pool

TOKENS_FILE = os.path.dirname(os.path.realpath(__file__))+'/.tokens'

parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="auth token" )
parser.add_argument( "-k","--keyword",help="keyword to search" )
parser.parse_args()
args = parser.parse_args()

t_tokens = []

if args.token:
    t_tokens = args.token.split(',')
else:
    if os.path.isfile(TOKENS_FILE):
        fp = open(TOKENS_FILE,'r')
        for line in fp:
            r = re.search( '^([a-f0-9]{40})$', line )
            if r:
                t_tokens.append( r.group(1) )

if not len(t_tokens):
    parser.error( 'auth token is missing' )

if args.keyword:
    keyword = args.keyword
else:
    parser.error( 'keyword is missing' )

sys.stdout.write( colored('[+] searching keyword: %s\n' % keyword, 'green') )


def searchUser( keyword, page=1 ):
    headers = {"Authorization":"token "+random.choice(t_tokens)}
    try:
        r = requests.get( 'https://api.github.com/search/users?q='+keyword+'&page='+str(page), headers=headers )
        return r.json()
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return False


def getUser( login ):
    headers = {"Authorization":"token "+random.choice(t_tokens)}
    try:
        r = requests.get( 'https://api.github.com/users/'+login, headers=headers )
        return r.json()
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return False


max_page = 0
total_found = 0
tab = Texttable( 300 )
tab.header( ['Login','Profile','Name','Email','Company','Public repos'] )

r_json = searchUser( keyword, 1 )
if len(r_json) and 'documentation_url' in r_json:
    print( colored("[-] error occurred!", 'red') )
    exit()

total_found = r_json['total_count']
max_page = math.ceil( r_json['total_count'] / 30)
sys.stdout.write( colored('[+] %d users found, %d pages.\n' %  (total_found,max_page), 'green') )
sys.stdout.write( '[+] retrieving user list...\n' )


def doGetUserList( page ):
    time.sleep( 200/1000 )
    sys.stdout.write( 'progress: %d/%d\r' %  (t_stats['n_current'],t_stats['n_max_page']) )
    sys.stdout.flush()
    t_stats['n_current'] = t_stats['n_current'] + 1
    r_json = searchUser( keyword, page )
    if len(r_json) and not 'documentation_url' in r_json:
        for u in r_json['items']:
            if u['login'] not in t_users:
                t_users.append( u['login'] )

t_users = []
t_profiles = []
t_stats = {
    'n_current': 0,
    'n_max_page': max_page
}

pool = Pool( 5 )
pool.map( doGetUserList, range(0,t_stats['n_max_page']) )
pool.close()
pool.join()

# print( t_users )

sys.stdout.write( colored('[+] %d login found.\n' %  (len(t_users)), 'green') )
sys.stdout.write( '[+] retrieving profiles...\n' )


t_stats['n_users'] = len(t_users)
t_stats['n_current'] = 0


def grabUserHtmlLight( ghaccount, login ):
    url = 'https://github.com/'+login

    try:
        r = requests.get( url, timeout=5 )
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return False

    if not 'Not Found' in r.text:
        r_org = re.search( 'data-hovercard-url="/orgs/([^/]*)/hovercard"', r.text, re.MULTILINE|re.IGNORECASE )
        if r_org:
            o = r_org.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )
        
        r_org = re.search( 'aria-label="Organization: ([^"]*)"', r.text, re.MULTILINE|re.IGNORECASE )
        if r_org:
            o = r_org.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )

        r_status = re.search( 'class="user-status-message-wrapper f6 mt-1 text-gray-dark ws-normal lh-condensed">\s*<div>.* at ([^<]*)', r.text, re.MULTILINE|re.IGNORECASE )
        if r_status and r_status.group(1).lower() not in ghaccount['orgs']:
            o = r_status.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )

        r_bio = re.search( 'js-user-profile-bio"><div>.* at ([^<]*)', r.text, re.MULTILINE|re.IGNORECASE )
        if r_bio and r_bio.group(1).lower() not in ghaccount['orgs']:
            o = r_bio.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )


def doGetProfile( login ):
    time.sleep( 200/1000 )
    sys.stdout.write( 'progress: %d/%d\r' %  (t_stats['n_current'],t_stats['n_users']) )
    sys.stdout.flush()
    t_stats['n_current'] = t_stats['n_current'] + 1
    r_json = getUser( login )
    if len(r_json) and not 'documentation_url' in r_json:
        tmp = {}
        tmp['login'] = r_json['login']
        tmp['html_url'] = r_json['html_url']
        tmp['name'] = r_json['name']
        tmp['email'] = r_json['email']
        tmp['public_repos'] = r_json['public_repos']
        # tmp['company'] = r_json['company']
        tmp['orgs'] = []
        if type(r_json['company']) is str:
            tmp['orgs'].append( r_json['company'] )
        grabUserHtmlLight( tmp, login )
        if len(tmp['orgs']):
            orgs = ','.join(tmp['orgs'])
        else:
            orgs = ''
        t_profiles.append( tmp )
        tab.add_row( [login,r_json['html_url'],r_json['name'],r_json['email'],orgs,r_json['public_repos']] )


pool = Pool( 5 )
pool.map( doGetProfile, t_users )
pool.close()
pool.join()

if len(t_profiles):
    print( tab.draw() )

exit()
