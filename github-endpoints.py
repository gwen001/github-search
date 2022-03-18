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
from urllib.parse import urlparse
from functools import partial
from colored import fg, bg, attr
from multiprocessing.dummy import Pool

TOKENS_FILE = os.path.dirname(os.path.realpath(__file__))+'/.tokens'
MIN_LENGTH = 5
_url_chars = '[a-zA-Z0-9\-\.\?\#\$&@%=_:/\]\[]'
_not_url_chars = '[^a-zA-Z0-9\-\.\?\#\$&@%=_:/\]\[]'
t_endpoints = []
t_exclude = [
    r'^http://$',
    r'^https://$',
    r'^javascript:$',
    r'^tel:$',
    r'^mailto:$',
    r'^text/javascript$',
    r'^application/json$',
    r'^application/javascript$',
    r'^text/plain$',
    r'^text/html$',
    r'^text/x-python$',
    r'^text/css$',
    r'^image/png$',
    r'^image/jpeg$',
    r'^image/x-icon$',
    r'^img/favicon.ico$',
    r'^application/x-www-form-urlencoded$',
    r'/Users/[0-9a-zA-Z\-\_]/Desktop',
    r'www.w3.org',
    r'schemas.android.com',
    r'www.apple.com',
    # r'^#',
    # r'^\?',
    # r'^javascript:',
    # r'^mailto:',
]
t_regexp = [
    r'[\'"\(].*(http[s]?://'+_url_chars+'*?)[\'"\)]',
    r'[\'"\(](http[s]?://'+_url_chars+'+)',

    r'[\'"\(]('+_url_chars+'+\.sdirect'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.htm'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.php'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.asp'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.js'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.xml'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.ini'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.conf'+_url_chars+'*)',
    r'[\'"\(]('+_url_chars+'+\.cfm'+_url_chars+'*)',

    r'href\s*[.=]\s*[\'"]('+_url_chars+'+)',
    r'src\s*[.=]\s*[\'"]('+_url_chars+'+)',
    r'url\s*[:=]\s*[\'"]('+_url_chars+'+)',

    r'urlRoot\s*[:=]\s*[\'"]('+_url_chars+'+)',
    r'endpoint[s]\s*[:=]\s*[\'"]('+_url_chars+'+)',
    r'script[s]\s*[:=]\s*[\'"]('+_url_chars+'+)',

    r'\.ajax\s*\(\s*[\'"]('+_url_chars+'+)',
    r'\.get\s*\(\s*[\'"]('+_url_chars+'+)',
    r'\.post\s*\(\s*[\'"]('+_url_chars+'+)',
    r'\.load\s*\(\s*[\'"]('+_url_chars+'+)',

    ### a bit noisy
    # r'[\'"](' + _url_chars + '+/' + _url_chars + '+)?[\'"]',
    # r'content\s*[.=]\s*[\'"]('+_url_chars+'+)',
]

def githubApiSearchCode( token, search, page, sort, order ):
    headers = { "Authorization":"token "+token }
    url = 'https://api.github.com/search/code?per_page=100&s=' + sort + '&type=Code&o=' + order + '&q=' + search + '&page=' + str(page)
    # print(">>> "+url)

    try:
        r = requests.get( url, headers=headers, timeout=5 )
        json = r.json()
        # print(r.json)
        # print(r.text)
        return json
    except Exception as e:
        print( "%s[-] error occurred: %s%s" % (fg('red'),e,attr(0)) )
        return False



def getRawUrl( result ):
    raw_url = result['html_url']
    raw_url = raw_url.replace( 'https://github.com/', 'https://raw.githubusercontent.com/' )
    raw_url = raw_url.replace( '/blob/', '/' )
    return raw_url


def readCode( regexp, confirm, display_source, display_relative, display_alldomains, result ):

    time.sleep( random.random() )

    url = getRawUrl( result )
    if url in t_history_urls:
        return

    str = ''
    t_local_endpoints = []
    t_history_urls.append( url )
    code = doGetCode( url )
    # print( code )
    # print( regexp )
    # print( confirm )
    # print( display_source )
    # print( display_relative )
    # print( display_alldomains )

    if code:
        if display_source:
            str = "\n%s>>> %s%s\n\n" % (fg('yellow'),result['html_url'],attr(0))
        matches = re.findall( regexp, code, re.IGNORECASE )
        if matches:
            # domain found in the code
            for r in t_regexp:
                # looking for endpoints
                edpt = re.findall( r, code, re.IGNORECASE )
                if edpt:
                    # endpoints found
                    for endpoint in edpt:
                        endpoint = endpoint.strip()
                        if len(endpoint) >= MIN_LENGTH:
                            # sys.stdout.write("%s\n" % endpoint)
                            # continue
                            goodbye = False
                            for exclude in t_exclude:
                                if re.match(exclude,endpoint,re.IGNORECASE):
                                    goodbye = True
                                    break
                            if goodbye:
                                continue
                            if endpoint.lower().startswith('http'):
                                is_relative = False
                            else:
                                is_relative = True
                            if is_relative and not display_relative:
                                continue
                            if endpoint in t_local_endpoints:
                                continue
                            # ???
                            # if not display_source and endpoint in t_endpoints:
                            #     continue
                            if not display_alldomains and not is_relative:
                                try:
                                    t_url_parse = urlparse( endpoint )
                                    t_host_parse = tldextract.extract( t_url_parse.netloc )
                                    domain = t_host_parse.domain
                                    # print(domain)
                                    sss = re.findall( regexp, t_url_parse.netloc, re.IGNORECASE )
                                    if not sss:
                                        continue
                                except Exception as e:
                                    sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )

                            t_endpoints.append( endpoint )
                            t_local_endpoints.append( endpoint )
                            str = str + ("%s\n" % endpoint)
                            # if display_source:
                            #     str = str + ("%s\n" % endpoint)
                            # else:
                            #     sys.stdout.write( "%s\n" % endpoint )

    # if display_source and len(t_local_endpoints):
    if len(t_local_endpoints):
        sys.stdout.write( str )



def doGetCode( url ):
    try:
        r = requests.get( url, timeout=5 )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False

    return r.text


parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="your github token (required)" )
parser.add_argument( "-d","--domain",help="domain you are looking for (required)" )
parser.add_argument( "-e","--extend",help="also look for <dummy>example.com", action="store_true" )
parser.add_argument( "-a","--all",help="displays urls of all other domains", action="store_true" )
parser.add_argument( "-r","--relative",help="also displays relative urls", action="store_true" )
parser.add_argument( "-s","--source",help="display urls where endpoints are found", action="store_true" )
parser.add_argument( "-v","--verbose",help="verbose mode, for debugging purpose", action="store_true" )
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

if args.relative:
    _relative = True
else:
    _relative = False

if args.all:
    _alldomains = True
else:
    _alldomains = False

t_sort_order = [
    { 'sort':'indexed', 'order':'desc',  },
    { 'sort':'indexed', 'order':'asc',  },
    { 'sort':'', 'order':'desc',  }
]

t_history = []
t_history_urls = []
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

# or simply ?
# _search = '"' + _domain + '"'
# print(_search)
# exit()
###


if args.extend:
    _regexp = r'(([0-9a-z_\-\.]+\.)?([0-9a-z_\-]+)?'+t_host_parse.domain+'([0-9a-z_\-\.]+)?\.[a-z]{1,5})'
    _confirm = t_host_parse.domain
else:
    _regexp = r'((([0-9a-z_\-\.]+)\.)?' + _domain.replace('.','\.')+')'
    _confirm = _domain


if args.verbose:
    print( "Search: %s" % _search )
    print( "Regexp: %s" % _regexp)
    print( "Confirm: %s" % _confirm)
    print( "Relative urls: %s" % _relative)
    print( "All domains: %s" % _alldomains)

for so in t_sort_order:

    page = 1

    if args.verbose:
        print( '\n----- %s %s\n' % (so['sort'],so['order']) )

    while True:

        if args.verbose:
            print("page %d" % page)

        time.sleep( random.random() )
        token = random.choice( t_tokens )
        t_json = githubApiSearchCode( token, _search, page, so['sort'], so['order'] )
        # print(t_json)

        if not t_json or 'documentation_url' in t_json:
            if args.verbose:
                print(t_json)
            t_tokens.remove(token)
            if len(t_tokens) == 0:
                exit()
            continue

        page = page + 1

        if 'items' in t_json and len(t_json['items']):
            pool = Pool( 30 )
            pool.map( partial(readCode,_regexp,_confirm,_source,_relative,_alldomains), t_json['items'] )
            pool.close()
            pool.join()
        else:
            break

        exit()
