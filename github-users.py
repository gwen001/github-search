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

TOKENS_FILE = '.tokens'

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
tab.header( ['login','html_url','name','email','company','public_repos'] )


for page in range(1,1000):
    r_json = searchUser( keyword, page )
    # print(r_json)
    if len(r_json) and 'documentation_url' in r_json:
        break

    if page == 1:
        total_found = r_json['total_count']
        max_page = math.ceil( r_json['total_count'] / 30)
        sys.stdout.write( colored('[+] %d users found.\n' %  (total_found), 'green') )
        sys.stdout.write( '[+] retrieving profiles...\n' )

    if not total_found:
        break
    
    sys.stdout.write( '[+] %d/%d pages\n' %  (page,max_page) )

    for u in r_json['items']:
        # print(u)
        time.sleep( 200/1000 )
        t_profile = getUser( u['login'] )
        # print(type(t_profile))
        # print(t_profile)
        if len(t_profile) and not 'documentation_url' in t_profile:
            tab.add_row( [t_profile['login'],t_profile['html_url'],t_profile['name'],t_profile['email'],t_profile['company'],t_profile['public_repos']] )

    if page >= max_page:
        break

if total_found:
    print( tab.draw() )
