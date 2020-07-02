#!/usr/bin/python2

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
from multiprocessing.dummy import Pool

TOKENS_FILE = os.path.dirname(os.path.realpath(__file__))+'/.tokens'
GITHUB_API_URL = 'https://api.github.com'

parser = argparse.ArgumentParser()
parser.add_argument( "-d","--dorks",help="dorks file (required)" )
parser.add_argument( "-t","--token",help="your github token (required)" )
parser.add_argument( "-o","--org",help="organization (required or -u)" )
parser.add_argument( "-e","--threads",help="maximum n threads, default 1" )
parser.add_argument( "-u","--user",help="user (required or -o)" )
parser.parse_args()
args = parser.parse_args()

t_tokens = []
t_orgs = []
t_users = []
t_dorks = []

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
    t_users = args.user.split(',')

if args.threads:
    threads = int(args.threads)
else:
    threads = 1

if args.org:
    t_orgs = args.org.split(',')

if not args.user and not args.org:
    parser.error( 'user or organization missing' )

if not args.dorks:
    parser.error( 'dorks file is missing' )

fp = open(args.dorks,'r')
for line in fp:
    t_dorks.append( line.strip() )

# print(t_tokens)
# print(t_orgs)
# print(t_users)
# print(t_dorks)

# max_minute_requests = len(t_tokens) * 30
# sleep_duration = 60 / max_minute_requests


def githubApiSearchCode( url ):
    # time.sleep( 0.800 )
    sys.stdout.write( 'progress: %d/%d\r' %  (t_stats['n_current'],t_stats['n_total_urls']) )
    sys.stdout.flush()
    t_stats['n_current'] =  t_stats['n_current'] + 1
    # i = random.SystemRandom().randint(0, t_stats['l_tokens'])
    i = t_stats['n_current'] % t_stats['l_tokens']
    # print(i)
    headers = {"Authorization": "token "+t_tokens[i]}
    # print(headers)

    try:
        r = requests.get( url, headers=headers, timeout=5 )
        json = r.json()
        # print(url)
        # print(json)
        if 'documentation_url' in json:
            print( colored("[-] error occurred: %s" % json['documentation_url'], 'red') )
        else:
            t_results_urls[url] = json['total_count']
            # print(json['total_count'])
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return 0


def __urlencode( str ):
	str = str.replace( ':', '%3A'  );
	str = str.replace( '"', '%22' );
	str = str.replace( ' ', '+' );
	return str


t_urls = {}
t_results = {}
t_results_urls = {}
t_stats = {
    # 'l_tokens': len(t_tokens)-1,
    'l_tokens': len(t_tokens),
    'n_current': 0,
    'n_total_urls': 0
}

for org in t_orgs:
    t_results[org] = []
    for dork in t_dorks:
        dork = 'org:' + org + ' ' + dork
        url = 'https://api.github.com/search/code?q=' + __urlencode(dork)
        t_results[org].append( url )
        t_urls[url] = 0

for user in t_users:
    t_results[user] = []
    for dork in t_dorks:
        dork = 'user:' + user + ' ' + dork
        # dork = '"' + dork + '"'
        url = 'https://api.github.com/search/code?q=' + __urlencode(dork)
        t_results[user].append( url )
        t_urls[url] = 0

# print(t_results)
# exit()

t_stats['n_total_urls'] = len(t_urls)
# exit()

sys.stdout.write( colored('%d organizations found.\n' %  len(t_orgs), 'green') )
sys.stdout.write( colored('%d users found.\n' %  len(t_users), 'green') )
sys.stdout.write( colored('%d dorks found.\n' %  len(t_dorks), 'green') )
sys.stdout.write( colored('%d urls generated.\n' %  len(t_urls), 'green') )
sys.stdout.write( '[+] running %d threads.\n' %  threads )

time.sleep( 1 )

pool = Pool( threads )
pool.map( githubApiSearchCode, t_urls )
pool.close()
pool.join()

for org in t_orgs:
    print( '>>>>> %s\n' % org )
    for url in t_results[org]:
        if url in t_results_urls:
            url2 = url.replace( 'https://api.github.com/search/code', 'https://github.com/search' ) + '&s=indexed&type=Code&o=desc'
            if t_results_urls[url] == 0:
                sys.stdout.write( colored('%s (%d)\n' %  (url2,t_results_urls[url]), 'white') )
            else:
                sys.stdout.write( '%s (%d)\n' %  (url2,t_results_urls[url]) )
        else:
            sys.stdout.write( colored('%s\n' % url2, 'red') )
    print('')

for user in t_users:
    print( '>>>>> %s\n' % user )
    for url in t_results[user]:
        if url in t_results_urls:
            url2 = url.replace( 'https://api.github.com/search/code', 'https://github.com/search' ) + '&s=indexed&type=Code&o=desc'
            if t_results_urls[url] == 0:
                sys.stdout.write( colored('%s (%d)\n' %  (url2,t_results_urls[url]), 'white') )
            else:
                sys.stdout.write( '%s (%d)\n' %  (url2,t_results_urls[url]) )
        else:
            sys.stdout.write( colored('%s\n' % url2, 'red') )
    print('')
