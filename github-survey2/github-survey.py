#!/usr/bin/python3

import os
import sys
import re
import time
import json
import argparse
import requests
import random
import base64
# import datetime
# import collections
import urllib.parse
# from pathlib import Path
from colored import fg, bg, attr
# from multiprocessing.dummy import Pool


MAX_RESULTS = 1000
TOKEN_COUNTER = 0
RESULTS_PER_PAGE = 100
REQUEST_DELAY = 4


def loadTokens():
    t_tokens = []
    gh_env =  os.getenv('GITHUB_TOKEN')

    if gh_env:
        t_tokens = gh_env.strip().split(',')
    else:
        tokens_file = os.path.dirname(os.path.realpath(__file__)) + '/.tokens'
        if os.path.isfile(tokens_file):
            t_tokens = open(tokens_file,'r').read().strip().split("\n")

    return t_tokens


def loadConfig():
    t_config = []
    config_file = os.path.dirname(os.path.realpath(__file__)) + '/config.json'

    try:
        with open(config_file) as jfile:
            t_config = json.load( jfile )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        exit()

    return t_config


def saveResults( t_save ):
    results_file = os.path.dirname(os.path.realpath(__file__)) + '/results.json'
    sys.stdout.write('%ssaving...%s\n' % (fg('dark_gray'),attr(0)) )

    try:
        with open(results_file, 'w') as jfile:
            json.dump( t_save, jfile, indent=4 )
        return True
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )

    return False


def getProjectsList( t_config ):
    if 'projects' in t_config:
        return t_config.keys()
    else:
        return False


def githubHeaders():
    global t_tokens
    global TOKEN_COUNTER
    token = t_tokens[TOKEN_COUNTER]
    # print(token)
    TOKEN_COUNTER = (TOKEN_COUNTER + 1) % len(t_tokens)
    return {
        "Accept": "application/vnd.github.v3+json",
        "Authorization": "token "+token,
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36"
    }


def removeToken( token ):
    global t_tokens
    global TOKEN_COUNTER

    if ' ' in token:
        t_tokens.remove( token.split(' ')[1] )
    else:
        t_tokens.remove( token )

    TOKEN_COUNTER = 0

    if len(t_tokens) == 0:
        sys.stdout.write( "%s[-] no more token%s\n" % (fg('red'),attr(0)) )
        exit()


def githubSearchCode( dork, page=1 ):
    global REQUEST_DELAY
    global RESULTS_PER_PAGE
    time.sleep( REQUEST_DELAY )
    try:
        u = 'https://api.github.com/search/code?sort=indexed&order=desc&per_page='+str(RESULTS_PER_PAGE)+'&page='+str(page)+'&q='+urllib.parse.quote(dork)
        print(u)
        t_headers = githubHeaders()
        r = requests.get( u, headers=t_headers, timeout=10 )
        t_json = r.json()
        if 'message' in t_json:
            sys.stdout.write( "%s[-]%s%s\n" % (fg('red'),t_json['message'],attr(0)) )
            if 'credentials' in t_json['message']:
                removeToken( t_headers['Authorization'] )
                githubSearchCode( dork, page )
            else:
                return False
        else:
            return t_json
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False


def githubCommitDate( item ):
    # time.sleep( 3 )
    try:
        commit_id = item['url'].split("=")[1]
        # print(commit_id)
        u = item['repository']['url']+'/git/commits/'+str(commit_id);
        # print(u)
        t_headers = githubHeaders()
        r = requests.get( u, headers=t_headers, timeout=10 )
        t_json = r.json()
        if 'sha' in t_json and t_json['sha'] == str(commit_id):
            commit_date = formatCommitDate( t_json['committer']['date'] )
            return commit_date
        else:
            sys.stdout.write( "%s[-] %s%s\n" % (fg('red'),t_json['message'],attr(0)) )
            if 'credentials' in t_json['message']:
                removeToken( t_headers['Authorization'] )
                githubCommitDate( item )
            else:
                return False
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False


def formatCommitDate( commit_date ):
    new_date = commit_date
    # new_date = commit_date.replace('T',' ').replace('Z','')
    return new_date


def githubContent( item ):
    try:
        u = item['git_url']
        # print(u)
        t_headers = githubHeaders()
        r = requests.get( u, headers=t_headers, timeout=10 )
        t_json = r.json()
        if 'sha' in t_json and t_json['sha'] == result['sha']:
            content = formatContent( t_json['content'] )
            return content
        else:
            sys.stdout.write( "%s[-] %s%s\n" % (fg('red'),t_json['message'],attr(0)) )
            if 'credentials' in t_json['message']:
                removeToken( t_headers['Authorization'] )
                githubContent( item )
            else:
                return False
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False


def formatContent( content ):
    new_content = content
    # new_content = base64.b64decode( content.replace('\n','') )
    return new_content


def isFiltered( item, t_exclude, t_filters ):
    # exclude string in the content
    if 'content' in t_filters:
        content = base64.b64decode( item['content'].replace('\n','') )
        for exclude in t_exclude['content']:
            r = re.search( str.encode(exclude), content )
            if r:
                sys.stdout.write('%sitem excluded, content:%s%s\n' % (fg('dark_gray'),exclude,attr(0)) )
                return True

    # exclude extension
    if 'extension' in t_filters:
        tmp = item['path'].split('.')
        l = len(tmp)
        if l > 1 and tmp[l-1] in t_exclude['extension']:
            sys.stdout.write('%sitem excluded, extension:%s%s\n' % (fg('dark_gray'),tmp[l-1],attr(0)) )
            return True

    # exclude filepath
    if 'filepath' in t_filters:
        full_path = item['repository']['full_name']+'/'+item['path']
        for exclude in t_exclude['filepath']:
            if full_path.startswith(exclude):
                sys.stdout.write('%sitem excluded, filepath:%s%s\n' % (fg('dark_gray'),exclude,attr(0)) )
                return True
            # r = re.search( exclude, full_path )
            # if r:
            #     return True

    return False


def excludeFusion( t_config, t_dork_exclude={} ):
    t_exclude = {
        'content': [],
        'extension': [],
        'filepath': []
    }

    if 'exclude' in t_config and 'content' in t_config['exclude']:
        t_exclude['content'] = t_config['exclude']['content']
    if 'content' in t_dork_exclude:
        t_exclude['content'] = t_exclude['content'] + t_dork_exclude['content']

    if 'exclude' in t_config and 'extension' in t_config['exclude']:
        t_exclude['extension'] = t_config['exclude']['extension']
    if 'extension' in t_dork_exclude:
        t_exclude['extension'] = t_exclude['extension'] + t_dork_exclude['extension']

    if 'exclude' in t_config and 'filepath' in t_config['exclude']:
        t_exclude['filepath'] = t_config['exclude']['filepath']
    if 'filepath' in t_dork_exclude:
        t_exclude['filepath'] = t_exclude['filepath'] + t_dork_exclude['filepath']

    return t_exclude


parser = argparse.ArgumentParser()
parser.add_argument( "-t","--token",help="your github token (required)" )
parser.add_argument( "-v","--verbose",help="verbose mode, default: off", action="store_true" )
parser.parse_args()
args = parser.parse_args()

t_tokens = []
if args.token:
    t_tokens = args.token.split(',')
else:
    t_tokens = loadTokens()

if not len(t_tokens):
    sys.stdout.write( "%s[-] auth token is missing%s\n" % (fg('red'),attr(0)) )
    exit()

REQUEST_DELAY = 60 / (30*len(t_tokens)) * 3

if args.verbose:
    verbose_mode = True
else:
    verbose_mode = False


t_save = {}
t_config = loadConfig()
t_projects = getProjectsList(t_config)


if not t_projects:
    sys.stdout.write( "%s[-] config key not found: projects%s\n" % (fg('red'),attr(0)) )
    exit()

for project,t_project in t_config['projects'].items():
    sys.stdout.write('########## '+project+' ##########\n')
    if not 'dorks' in t_project:
        continue

    t_save[project] = {}

    for dork,t_dork in t_project['dorks'].items():
        t_save[project][dork] = []
        run = True
        page = 1
        n_results = 0
        if 'exclude' in t_dork:
            t_exclude = excludeFusion( t_config, t_dork['exclude'] )
        else:
            t_exclude = excludeFusion( t_config )

        while run:
            t_results = githubSearchCode( dork, page )
            if t_results is False:
                run = False
                continue
            if not 'items' in t_results:
                # print("no item")
                run = False
                continue
            if 'total_count' in t_results and t_results['total_count'] <= 0:
                # print("no item")
                run = False
                continue

            sys.stdout.write('%stotal_count:%d, items found:%d%s\n' % (fg('dark_gray'),t_results['total_count'],len(t_results['items']),attr(0)) )

            for result in t_results['items']:
                t_item = {}

                if t_dork['last_sha'] == result['sha']:
                    sys.stdout.write('%slast sha reached:%s%s\n' % (fg('dark_gray'),t_dork['last_sha'],attr(0)) )
                    run = False
                    break
                if n_results >= MAX_RESULTS:
                    sys.stdout.write('%smax_results reached:%d%s\n' % (fg('dark_gray'),MAX_RESULTS,attr(0)) )
                    run = False
                    break

                t_item['name'] = result['name']
                t_item['path'] = result['path']
                t_item['sha'] = result['sha']
                t_item['url'] = result['url']
                t_item['git_url'] = result['git_url']
                t_item['html_url'] = result['html_url']
                t_item['repository'] = {}
                t_item['repository']['id'] = result['repository']['id']
                t_item['repository']['name'] = result['repository']['name']
                t_item['repository']['full_name'] = result['repository']['full_name']
                t_item['repository']['private'] = result['repository']['private']
                t_item['repository']['owner'] = {}
                t_item['repository']['owner']['id'] = result['repository']['owner']['id']
                t_item['repository']['owner']['login'] = result['repository']['owner']['login']
                t_item['repository']['owner']['avatar_url'] = result['repository']['owner']['avatar_url']
                t_item['repository']['owner']['html_url'] = result['repository']['owner']['html_url']
                t_item['repository']['owner']['repos_url'] = result['repository']['owner']['repos_url']
                t_item['repository']['owner']['organizations_url'] = result['repository']['owner']['organizations_url']

                is_filtered = isFiltered( t_item, t_exclude, ['filepath','extension'] )
                if not is_filtered:
                    t_item['content'] = githubContent( result )
                    is_filtered = isFiltered( t_item, t_exclude, ['content'] )
                    if not is_filtered:
                        t_item['commit_date'] = githubCommitDate( result )
                        t_save[project][dork].append( t_item )
                        n_results = n_results + 1

            page = page + 1

        # save after every dorks
        saveResults( t_save )

#     # save after every project
#     saveResults( t_save )

# # final save
# saveResults( t_save )
