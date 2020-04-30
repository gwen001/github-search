#!/usr/bin/python3

# I don't believe in license.
# You can do whatever you want with this program.

import os
import sys
import re
import time
import json
import requests
import random
import argparse
import urllib.parse
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


def readCode( search_regexp, t_regexp, result ):

    time.sleep( random.random() )

    url = getRawUrl( result )
    if url in t_history_urls:
        return

    output = ''
    t_history_urls.append( url )
    code = doGetCode( url )
    t_local_history = []
    # sys.stdout.write( ">>> calling %s\n" % url )
    
    if not code:
        return False
    
    search_matches = re.findall( search_regexp, code )

    if search_matches:
        color = 'white'
        regexp_color = 'light_green'
    else:
        color = 'light_gray'
        regexp_color = 'dark_green'

    for regexp in t_regexp_compiled:

        r = re.findall( regexp, code )

        if r:
            if not len(output):
                output = output + ("%s>>> %s%s\n\n" % (fg('yellow'),result['html_url'],attr(0)) )
            for rr in r:
                output = output + ('%s%s%s'%(fg(color),rr[0].lstrip(),attr(0))) + ('%s%s%s'%(fg(regexp_color),rr[1],attr(0))) + ('%s%s%s'%(fg(color),rr[-1].rstrip(),attr(0))) + "\n"

    if len(output.strip()):
        sys.stdout.write( "%s\n" % output )


def doGetCode( url ):

    try:
        r = requests.get( url, timeout=5 )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False

    return r.text


parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="auth token (required)" )
parser.add_argument( "-s","--search",help="search term you are looking for (required)" )
parser.add_argument( "-e","--extend",help="also look for <dummy>example.com", action="store_true" )
parser.add_argument( "-r","--regexp",help="regexp to search, default is SecLists secret-keywords list (can be a tomnomnom gf file)" )
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

if args.search:
    _search = args.search
    _search_encoded = urllib.parse.quote( args.search )
else:
    parser.error( 'search term is missing' )

t_regexp = []
if args.regexp:
    if os.path.isfile(args.regexp):
        with open(args.regexp) as json_file:
            data = json.load(json_file)
        if 'pattern' in data:
            t_regexp.append( data['pattern'] )
        elif 'patterns' in data:
            for r in data['patterns']:
                t_regexp.append( r )
    else:
        t_regexp.append( args.regexp )
else:
    t_regexp = [ "(ConsumerKey|ConsumerSecret|DB_USERNAME|HEROKU_API_KEY|HOMEBREW_GITHUB_API_TOKEN|JEKYLL_GITHUB_TOKEN|PT_TOKEN|SESSION_TOKEN|SF_USERNAME|SLACK_BOT_TOKEN|access-token|access_token|access_token_secret|accesstoken|api-key|api_key|api_secret_key|api_token|auth_token|authkey|authorization|authorization_key|authorization_token|authtoken|aws_access_key_id|aws_secret_access_key|bearer|bot_access_token|bucket|client-secret|client_id|client_key|client_secret|clientsecret|consumer_key|consumer_secret|dbpasswd|encryption-key|encryption_key|encryptionkey|id_dsa|irc_pass|key|oauth_token|pass|password|private_key|private_key|privatekey|secret|secret-key|secret_key|secret_token|secretkey|secretkey|session_key|session_secret|slack_api_token|slack_secret_token|slack_token|ssh-key|ssh_key|sshkey|token|username|xoxa-2|xoxr|private-key)\s*[:=>]\s*" ]

l_regexp = len(t_regexp)
if not l_regexp:
    parser.error( 'regexp is missing' )

t_regexp_compiled = []
for regexp in t_regexp:
    t_regexp_compiled.append( re.compile(r'(.{0,100})('+regexp+')(.{0,100})', re.IGNORECASE) )

stop = 0
page = 1
search_regexp = re.compile(r''+_search+'', re.IGNORECASE)
t_history = []
t_history_urls = []

while True:

    time.sleep( random.random() )
    t_json = githubApiSearchCode( _search_encoded, page )
    # print(page)
    page = page + 1

    if not t_json or 'documentation_url' in t_json or not 'items' in t_json or not len(t_json['items']):
        stop = stop + 1
        if stop == 3:
            break
        continue

    pool = Pool( 30 )
    pool.map( partial(readCode,search_regexp,t_regexp), t_json['items'] )
    pool.close()
    pool.join()
