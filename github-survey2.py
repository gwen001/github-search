#!/usr/bin/python3.5

# This script checks the date and the sha of the commits instead of the number of results
# and also integrates the exclude list

import os
import sys
import re
import time
import json
import base64
import requests
import argparse
import random
import datetime
import collections
import urllib.parse
from functools import partial
from pathlib import Path
from colored import fg, bg, attr
from multiprocessing.dummy import Pool



########### INIT
parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="auth token", action="append" )
parser.add_argument( "-p","--page",help="n max page" )
parser.add_argument( "-c","--config",help="config file, default: ~/.config/github-survey.json" )
parser.parse_args()
args = parser.parse_args()

if args.page:
    n_max_page = int(args.page)
else:
    n_max_page = 5
n_max_page = n_max_page - 1 # because first page is 0

if args.token:
    t_tokens = args.token
else:
    t_tokens = []
    tokens_file = os.path.dirname(os.path.realpath(__file__))+'/.tokens'
    if os.path.isfile(tokens_file):
        fp = open(tokens_file,'r')
        for line in fp:
            r = re.search( '^([a-f0-9]{40})$', line )
            if r:
                t_tokens.append( r.group(1) )

if not len(t_tokens):
    parser.error( 'auth token is missing' )



########### LOAD CONFIG
if args.config:
    config_file = args.config
else:
    config_file = str(Path.home()) + '/.config/github-survey.json'

try:
    with open(config_file) as jfile:
        t_config = json.load( jfile )
except Exception as e:
    sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
    exit()

if 'github_dorks' in t_config:
    t_old_values = t_config['github_dorks'].copy()
else:
    sys.stdout.write( "%s[-] error occurred: no dorks configured%s\n" % (fg('red'),attr(0)) )
    exit()

if not 'n_multiproc' in t_config:
    t_config['n_multiproc'] = 10

if not 'exclude' in t_config:
    t_config['exclude'] = {
        'filepath': [],
        'content': [],
        'extension': [],
    }



########### GITHUB REQUESTS
def githubApiSearchCode( dork, page ):
    token = random.choice( t_tokens )
    headers = {"Authorization": "token "+token}
    # print( headers )

    u = 'https://api.github.com/search/code?sort=indexed&order=desc&page=' + str(page) + '&q=' + urllib.parse.quote(dork)
    print( u )

    try:
        r = requests.get( u, headers=headers, timeout=5 )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False

    if r:
        t_json = r.json()
        if 'total_count' in t_json:
            return t_json
        else:
            return False
    else:
        return False

def doGetCode( result ):
    token = random.choice( t_tokens )
    headers = {"Authorization": "token "+token}
    # print( headers )

    try:
        print( result['git_url'] )
        r = requests.get( result['git_url'], headers=headers, timeout=5 )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False

    if r:
        t_json = r.json()
        if 'sha' in t_json:
            if len(t_json['content']):
                result['code'] = base64.b64decode( t_json['content'].replace('\n','') )
            else:
                result['code'] = ''
            return True
        else:
            return False
    else:
        return False

def doGetCommitDate( result ):
    token = random.choice( t_tokens )
    headers = {"Authorization": "token "+token}
    # print( headers )

    commit_id = result['url'].split('=')[-1];
    result['commit_url'] = result['repository']['url'] + '/git/commits/' + commit_id
    print( result['commit_url'] )

    try:
        r = requests.get( result['commit_url'], headers=headers, timeout=5 )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False

    if r:
        t_json = r.json()
        if 'sha' in t_json:
            result['commit_date'] = t_json['committer']['date']
            return True
        else:
            return False
    else:
        return False



########### RESULTS TREATMENT
def mergeExclude( t_global_exclude, t_dork ):
    t_exclude = t_global_exclude

    if not 'exclude' in t_dork:
        return t_exclude
    
    if 'filepath' in t_dork['exclude']:
        t_exclude['filepath'] = t_exclude['filepath'] + t_dork['exclude']['filepath']
    if 'filepath' in t_dork['exclude']:
        t_exclude['content'] = t_exclude['content'] + t_dork['exclude']['content']
    if 'filepath' in t_dork['exclude']:
        t_exclude['extension'] = t_exclude['extension'] + t_dork['exclude']['extension']
    
    return t_exclude

def filterResults( t_results, t_exclude, t_filters ):
    t_filtered = []

    for result in t_results:
        r = isFiltered( result, t_exclude, t_filters )
        # print( result['repository']['full_name'] + '/' + result['path'] )
        # print( r )
        if not r:
            t_filtered.append( result )

    return t_filtered;

def isFiltered( result, t_exclude, t_filters ):
    # exclude string in the content
    if 'content' in t_filters:
        for exclude in t_exclude['content']:
            if exclude.encode() in result['code']:
                return True

    # exclude extension
    if 'extension' in  t_filters:
        for exclude in t_exclude['extension']:
            if result['path'].endswith('.'+exclude):
                return True

    # exclude filepath
    if 'filepath' in t_filters:
        full_path = result['repository']['full_name'] + '/' + result['path']
        for exclude in t_exclude['filepath']:
            if full_path.startswith( exclude ):
                return True

    return False

def getCodes( t_filtered ):
    pool = Pool( t_config['n_multiproc'] )
    pool.map( doGetCode, t_filtered )
    pool.close()
    pool.join()

def getCommitDates( t_filtered ):
    pool = Pool( t_config['n_multiproc'] )
    pool.map( doGetCommitDate, t_filtered )
    pool.close()
    pool.join()

def testDork( n_max_page, dork ):
    print( dork )
    run = True
    page = 0
    t_result = []
    t_dork = t_config['github_dorks'][dork]

    if not type(t_dork) is dict or not len(t_dork):
        t_config['github_dorks'][dork] = {
            'title': 'github search code \'' + dork + '\'',
            'info': 'https://github.com/search?o=desc&s=indexed&type=Code&q=' + urllib.parse.quote(dork),
            'last_sha': '',
            'data': 0,
            'exclude': {
                'filepath': [],
                'content': [],
                'extension': []
            }
        }
        t_dork = t_config['github_dorks'][dork]
        with open(config_file, 'w') as jfile:
            json.dump( t_config, jfile, indent=4 )


    if 'last_sha' in t_dork:
        last_sha = t_dork['last_sha']
    else:
        last_sha = '1'

    while run:
        t_json = githubApiSearchCode( dork, page )
        if not t_json:
            break
    
        n_results = len( t_json['items'] )
        if not n_results:
            break
        
        for result in t_json['items']:
            if result['sha'] == last_sha:
                # print('last sha found!')
                run = False
                break
            else:
                t_result.append( result )

        page = page + 1
        if page > n_max_page:
            break
    
    t_exclude = mergeExclude( t_config['exclude'], t_dork )
    t_filtered = filterResults( t_result, t_exclude, ['filepath','extension'] )
    getCodes( t_filtered )
    t_filtered = filterResults( t_filtered, t_exclude, ['content'] ) # yes yes again ! (content filtering)
    # getCommitDates( t_filtered )
    # print( t_filtered )

    if len(t_filtered):
        t_final[dork] = t_filtered


########### MAIN LOOP
t_final = {}

# testDork( n_max_page, 'uber' )

pool = Pool( t_config['n_multiproc'] )
pool.map( partial(testDork,n_max_page), t_config['github_dorks'].keys() )
pool.close()
pool.join()



# ########### SLACK NOTIF
def san( str ):
    return str.replace('<','&lt;').replace('>','&gt;').replace('&','&amp;')

def sendSlackNotif( slack_webhook, t_attachments ):
    headers = {"Content-Type": "application/json"}
    t_datas = {
        'text': san( "*---------------- " + datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " ----------------*\n\n" ),
        'attachments': t_attachments
    }
    # print(json.dumps(t_datas))

    try:
        r = requests.post( slack_webhook, data=json.dumps(t_datas), headers=headers, timeout=5 )
        # print(r)
    except Exception as e:
        return


if 'slack_webhook' in t_config:
    message = ''
    t_attachments = []

    for dork in t_final.keys():
        t_urls = []
        for result in t_final[dork]:
            t_urls.append( result['html_url'] )
        attachment = {
            'pretext': san( t_config['github_dorks'][dork]['title'] + ': +' + str(len(t_final[dork])) ),
            'title': san( t_config['github_dorks'][dork]['info'] ),
            'title_link': san( t_config['github_dorks'][dork]['info'] ),
            'text': san( '```' + "\n".join(t_urls) + '```' )
        }
        t_attachments.append( attachment )
        # message = message + t_config['github_dorks'][dork]['title'] + ': +' + str(len(t_final[dork])) + "\n" + t_config['github_dorks'][dork]['info'] + "\n\n"

    if len(t_attachments):
        # print(message)
        # message = "*---------------- " + datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " ----------------*\n\n" + message
        # print(message)
        sendSlackNotif( t_config['slack_webhook'], t_attachments )

