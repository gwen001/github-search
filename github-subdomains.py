#!/usr/bin/python3.5

# I don't believe in license.
# You can do whatever you want with this program.

import os
import sys
import re
import time
import requests
import random
import argparse
from colored import fg, bg, attr
from multiprocessing.dummy import Pool

TOKENS_FILE = '.tokens'


def githubApiSearchCode( search, page ):
    headers = {"Authorization":"token "+random.choice(t_tokens)}
    url = 'https://api.github.com/search/code?s=indexed&type=Code&o=desc&q=' + search + '&page=' + str(page)
    # print(url)
    
    try:
        r = requests.get( url, headers=headers, timeout=5 )
        json = r.json()
        return json
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return False


def getRawUrl( result ):
    raw_url = result['html_url'];
    raw_url = raw_url.replace( 'https://github.com/', 'https://raw.githubusercontent.com/' )
    raw_url = raw_url.replace( '/blob/', '/' )
    return raw_url;


def readCode( result ):
    url = getRawUrl( result )
    code = doGetCode( url )
    # print(code)
    
    if code:
        matches = re.findall( r'[0-9a-zA-Z_\-\.]+\.'+_domain, code )
        if matches:
            for sub in  matches:
                if not sub in t_history:
                    t_history.append( sub )
                    print( sub )


def doGetCode( url ):
    # print( url )
    try:
        r = requests.get( url, timeout=5 )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False

    return r.text


parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="auth token (required)" )
parser.add_argument( "-d","--domain",help="domain you already know (required or -c)" )
parser.parse_args()
args = parser.parse_args()

t_tokens = []
if args.token:
    t_tokens = args.token.split(',')
else:
    if os.path.isfile(TOKENS_FILE):
        fp = open(TOKENS_FILE,'r')
        t_tokens = fp.read().split("\n")
        fp.close()

if not len(t_tokens):
    parser.error( 'auth token is missing' )

if args.domain:
    _domain = args.domain
else:
    parser.error( 'domain is missing' )

t_history = []
page = 1
_search = '"' + _domain + '"'

# for page in range(1,10):
while True:
    time.sleep( 1 )
    t_json = githubApiSearchCode( _search, page )
    page = page + 1
    if not t_json or 'documentation_url' in t_json or not 'items' in t_json or not len(t_json['items']):
        break
    # print(t_json)

    pool = Pool( 30 )
    pool.map( readCode, t_json['items'] )
    pool.close()
    pool.join()
