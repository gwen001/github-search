#!/usr/bin/python3

# I don't believe in license.
# You can do whatever you want with this program.

import os
import sys
import re
import time
import requests
import random
import argparse
from functools import partial
from colored import fg, bg, attr
from multiprocessing.dummy import Pool


TOKENS_FILE = os.path.dirname(os.path.realpath(__file__))+'/.tokens'


def githubApiSearchCode( search, page ):
    headers = {"Authorization":"token "+random.choice(t_tokens)}
    url = 'https://api.github.com/search/code?s=indexed&type=Code&o=desc&q=' + search + '&page=' + str(page)
    # print(url)

    try:
        r = requests.get( url, headers=headers, timeout=5 )
        json = r.json()
        return json
    except Exception as e:
        print( "%s[-] error occurred: %s%s" % (fg('red'),e,attr(0)) )
        return False


def getRawUrl( result ):
    raw_url = result['html_url'];
    raw_url = raw_url.replace( 'https://github.com/', 'https://raw.githubusercontent.com/' )
    raw_url = raw_url.replace( '/blob/', '/' )
    return raw_url;


def readCode( domain_regexp, source, result ):

    time.sleep( random.random() )

    url = getRawUrl( result )
    # print(url)
    if url in t_history_urls:
        return

    output = ''
    t_history_urls.append( url )
    code = doGetCode( url )
    t_local_history = []
    # sys.stdout.write( ">>> calling %s\n" % url )

    if code:
        matches = re.findall( domain_regexp, code, re.IGNORECASE )
        if matches:
            for sub in  matches:
                sub = sub[0].replace('2F','').lower().strip()
                if len(sub) and not sub in t_local_history:
                    t_local_history.append(sub)
                    if source:
                        if not len(output):
                            output = output + ("%s>>> %s%s\n\n" % (fg('yellow'),result['html_url'],attr(0)) )
                        t_history.append( sub )
                        output = output + ("%s\n" % sub)
                    elif not sub in t_history:
                        t_history.append( sub )
                        output = output + ("%s\n" % sub)

    if len(output.strip()):
        sys.stdout.write( "%s\n" % output )


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
parser.add_argument( "-d","--domain",help="domain you are looking for (required)" )
parser.add_argument( "-e","--extend",help="also look for <dummy>example.com", action="store_true" )
parser.add_argument( "-s","--source",help="display first url where subdomains are found", action="store_true" )
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

if args.source:
    _source = True
else:
    _source = False

if args.domain:
    _domain = args.domain
else:
    parser.error( 'domain is missing' )

t_history = []
t_history_urls = []
page = 1
_search = '"' + _domain + '"'

### this is a test, looks like we got more result that way
import tldextract
t_host_parse = tldextract.extract( _domain )

if args.extend:
    # which one is
    _search = '"' + t_host_parse.domain + '"'
else:
    # the most effective ?
    _search = '"' + t_host_parse.domain + '.' + t_host_parse.suffix + '"'

# or simply
# _search = '"' + _domain + '"'
# print( t_host_parse )
# exit()
###

# egrep -io "[0-9a-z_\-\.]+\.([0-9a-z_\-]+)?`echo $h|awk -F '.' '{print $(NF-1)}'`([0-9a-z_\-\.]+)?\.[a-z]{1,5}"


if args.extend:
    # domain_regexp = r'[0-9a-zA-Z_\-\.]+' + _domain.replace('.','\.')
    domain_regexp = r'([0-9a-z_\-\.]+\.([0-9a-z_\-]+)?'+t_host_parse.domain+'([0-9a-z_\-\.]+)?\.[a-z]{1,5})'
else:
    domain_regexp = r'(([0-9a-z_\-\.]+)\.' + _domain.replace('.','\.')+')'
# print(domain_regexp)

stop = 0
# for page in range(1,10):
while True:

    time.sleep( random.random() )
    t_json = githubApiSearchCode( _search, page )
    # print(t_json)
    page = page + 1

    if not t_json or 'documentation_url' in t_json or not 'items' in t_json or not len(t_json['items']):
        stop = stop + 1
        if stop == 3:
            break
        continue

    pool = Pool( 30 )
    pool.map( partial(readCode,domain_regexp,_source), t_json['items'] )
    pool.close()
    pool.join()
