#!/usr/bin/python2.7

# I don't believe in license.
# You can do whatever you want with this program.

import os
import sys
import json
import time
import re
import requests
import argparse
import random
from termcolor import colored
from texttable import Texttable

TOKENS_FILE = '.tokens'
GITHUB_API_URL = 'https://api.github.com'

parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="auth token" )
parser.add_argument( "-o","--org",help="organization" )
parser.add_argument( "-u","--user",help="user" )
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

if args.user:
    gh_user = args.user
else:
    gh_user = ''

if args.org:
    gh_org = args.org
else:
    gh_org = ''

if gh_org == '' and gh_user == '':
    parser.error( 'user or organization missing' )


def ghAPI( endpoint, paging=True ):
#     print( endpoint )
    error = 0
    page = 1
    run = True
    headers = {"Authorization":"token "+random.choice(t_tokens)}
    datas = []
    
    while run:
        try:
            u = GITHUB_API_URL+endpoint
            # print(u)
            if paging:
                u = u + '?page='+str(page)
            print( u )
            r = requests.get( u, headers=headers )
            page = page + 1
            if len(r.text):
                if type(r.json()) is dict and 'documentation_url' not in r.json():
                    datas.append( r.json() )
                elif type(r.json()) is list and 'documentation_url' not in r.json():
                    datas = datas + r.json()
                else:
                    run = False
            else:
                run = False
            if not len(r.text) or not len(r.json()) or not paging:
                run = False
        except Exception as e:
            error = error + 1
            print( colored("[-] error occurred: %s" % e, 'red') )
        
        if error:
            run = False
        
    return datas

if gh_org:
    gh_owner = gh_org
    r = ghAPI( '/orgs/'+gh_org+'/repos' )
elif gh_user:
    gh_owner = gh_user
    r = ghAPI( '/users/'+gh_user+'/repos' )

n_notfork = 0
print( colored('[+] %d repositories found.' % len(r), 'green') )
for repo in r:
    if not repo['fork']:
        n_notfork = n_notfork + 1

print( '[+] %d are not fork.' % n_notfork )
print( '[+] grabbing contributors...\n' )
t_collab = {}
i = 0

for repo in r:
    # if len(t_collab) >= 10:
    #     break
    if not repo['fork']:
        i = i + 1
        r = ghAPI( '/repos/'+gh_owner+'/'+repo['name']+'/contributors' )
        sys.stdout.write( '%d/%d https://github.com/%s/%s\n' % (i,n_notfork,gh_owner,repo['name']) )
        if type(r) is list:
            for collab in r:
                sys.stdout.write( "%s (%d)\n" % (collab['login'],collab['contributions']) )
                if not collab['login'] in t_collab:
                    t_collab[ collab['login'] ] = 0
                t_collab[ collab['login'] ] = t_collab[ collab['login'] ] + collab['contributions']
        else:
            sys.stdout.write("-\n")

        sys.stdout.write("\n")

print( colored('[+] %d contributors found, reading profiles...\n' % len(t_collab),'green') )
tab = Texttable( 300 )
tab.header( ['Contributions','Profile','Name','Company'] )
# tab.set_max_width( 100 )

for login,collab in sorted(t_collab.items(),reverse=True, key=lambda item:item[1]):
    time.sleep( 200/1000 )
    r = ghAPI( '/users/'+login, False )
    if not type(r) is bool:
        r = r[0]
        if login.lower() == gh_user.lower():
            l = colored( 'https://github.com/'+login,'yellow' )
        else:
            l = 'https://github.com/'+login
        tab.add_row( [collab,l,r['name'],r['company']] )

print( tab.draw() )
print("\n")
