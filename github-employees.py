#!/usr/bin/python3

import os
import sys
import time
import re
import requests
import argparse
import unidecode
import itertools
from termcolor import colored
from texttable import Texttable
from goop import goop


def generateLogins( name ):
    t_todo = []
    altnames = []

    name = unidecode.unidecode( name ).lower()
    t_name = name.split( ' ' )
    t_todo.append( t_name )

    for n1 in t_name:
        tmp = []
        tmp.append( n1[0] )
        for n2 in t_name:
            if n1 != n2:
                tmp.append( n2 )
        t_todo.append( tmp )

    for todo in t_todo:
        t_perms = list( itertools.permutations(todo) )
        for p in t_perms:
            altnames.append( ''.join(p) )
            altnames.append( '-'.join(p) )

    return altnames


GG_SEARCH = 'site:linkedin.com/in '
GITHUB_API_URL = 'https://api.github.com'

parser = argparse.ArgumentParser()
parser.add_argument( "-f","--fbcookie",help="your facebook cookie" )
parser.add_argument( "-c","--company",help="company" )
parser.add_argument( "-p","--page",help="n page to grab, default 10" )
parser.parse_args()
args = parser.parse_args()

if args.page:
    page = int(args.page)
else:
    page = 10

if args.fbcookie:
    fb_cookie = args.fbcookie
else:
    parser.error( 'facebook cookie is missing' )

if args.company:
    company = args.company
else:
    parser.error( 'company is missing' )

sys.stdout.write( colored('[+] looking for employees at company: %s\n' %  company.upper(), 'green') )
gg_search = GG_SEARCH + company
t_results = []

for i in range(0,page):
    sys.stdout.write( '[+] grabbing page %d/%d...\n' %  ((i+1),page) )
    s = goop.search( gg_search, fb_cookie, page=i )
    for e in s:
        t_results.append( s[e] )

sys.stdout.write( colored('[+] %d employees found.\n' %  len(t_results), 'green') )
sys.stdout.write( '[+] generating logins...\n'  )

n_altnames = 0
print( t_results )
# exit()

for emp in t_results:
    tmp = emp['text'].split( '-' )
    tmp[0] = re.sub( '[^a-zA-Z ]', '', tmp[0] )
    tmp[0] = re.sub( '\s+', ' ', tmp[0] )
    tmp2 = tmp[0].strip().split( ' ' )
    emp['name'] = ' '.join(tmp2[0:3]) # we don't want the permutation function runs forever
    if len(tmp) > 1:
        emp['job'] = tmp[1].strip()
    else:
        emp['job'] = ''
    if len(tmp) > 2:
        emp['company'] = tmp[2].strip().replace( ' | LinkedIn', '' ).strip()
    else:
        emp['company'] = ''
    emp['altnames'] = generateLogins( emp['name'] )
    # tmp = emp['url'].split('/')
    # emp['altnames'].append( tmp[-1] )
    emp['ghfound'] = {}
    n_altnames = n_altnames + len(emp['altnames'])

# t_results = [ t_results[0] ]
sys.stdout.write( colored('[+] %d alternative logins created.\n' %  n_altnames, 'green') )
sys.stdout.write( '[+] testing logins on Github...\n'  )

n = -1
n_ghfound = 0
run = True

for emp in t_results:
    # if n_ghfound >= 5:
    #     break
    if not run:
        break
    
    for login in emp['altnames']:

        n = n + 1
        if (n%100) == 0 and n > 0:
            sys.stdout.write( 'progress: %d/%d\n' %  (n,n_altnames) )
        if not run:
            break
        
        url = 'https://github.com/'+login

        try:
            r = requests.get( url, timeout=5 )
        except Exception as e:
            # run = False
            print( colored("[-] error occurred: %s" % e, 'red') )
            time.sleep( 2 )
            break

        if not 'Not Found' in r.text:
            sys.stdout.write( colored('%s\n' %  url, 'cyan') )
            r_repo = re.search( 'tab=repositories">\s*Repositories\s*<span class="Counter hide-lg hide-md hide-sm">\s*([0-9]*)\s*</span>', r.text, re.MULTILINE|re.IGNORECASE )
            if r_repo and int(r_repo.group(1)) > 0:
                n_ghfound = n_ghfound + 1
                emp['ghfound'][login] = {}
                emp['ghfound'][login]['name'] = ''
                emp['ghfound'][login]['repo'] = r_repo.group(1)
                emp['ghfound'][login]['url'] = url
                emp['ghfound'][login]['orgs'] = []

                r_name = re.search( 'itemprop="name">([^<]*)</span>', r.text, re.MULTILINE|re.IGNORECASE )
                if r_name:
                    emp['ghfound'][login]['name'] = r_name.group(1)
                r_org = re.search( 'data-hovercard-url="/orgs/([^/]*)/hovercard"', r.text, re.MULTILINE|re.IGNORECASE )
                if r_org and r_org.group(1).lower() not in emp['ghfound'][login]['orgs']:
                    emp['ghfound'][login]['orgs'].append( r_org.group(1).lower() )
                r_org = re.search( 'aria-label="Organization: ([^"]*)"', r.text, re.MULTILINE|re.IGNORECASE )
                if r_org and r_org.group(1).lower() not in emp['ghfound'][login]['orgs']:
                    emp['ghfound'][login]['orgs'].append( r_org.group(1).lower() )
        else:
            sys.stdout.write( colored('%s\n' %  url, 'white') )

sys.stdout.write( colored('[+] %d profiles found.\n' %  n_ghfound, 'green') )

tab = Texttable( 300 )
tab.header( ['LinkedIn title','Github profiles (repo)','Github name','Github organizations'] )

for emp in t_results:
    if len(emp['ghfound']):
        str_u = ''
        str_n = ''
        str_o = ''
        str_t = emp['name'] + ' - ' + emp['job'] + ' - ' + emp['company'] + "\n" + emp['url']
        for login,profile in emp['ghfound'].items():
            str_u = str_u + profile['url'] + ' (' + str(profile['repo']) + ')' + "\n"
            str_n = str_n + profile['name'] + "\n"
            if len(profile['orgs']):
                str_o = str_o + ",".join( profile['orgs'] )
            str_o = str_o + "\n"
        tab.add_row( [str_t,str_u,str_n,str_o] )

print( tab.draw() )
print("\n")


