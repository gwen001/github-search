#!/usr/bin/python3.5

import os
import sys
import re
import time
import json
import requests
import random
import datetime
import collections
import urllib.parse
from pathlib import Path
from colored import fg, bg, attr
from multiprocessing.dummy import Pool



########### LOAD CONFIG
# config_file = os.path.dirname(os.path.realpath(__file__)) + '/github-survey.json'
config_file = str(Path.home()) + '/.config/github-survey.json'

try:
    with open(config_file) as jfile:
        t_config = json.load( jfile )
except Exception as e:
    sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
    exit()

if not 'n_multiproc' in t_config:
    t_config['n_multiproc'] = 10

if 'github_dorks' in t_config:
    t_old_values = t_config['github_dorks'].copy()
else:
    sys.stdout.write( "%s[-] error occurred: no dorks configured%s\n" % (fg('red'),attr(0)) )
    exit()

# t_new_values = t_old_values.copy()



########### GITHUB SEARCH CODE
def loadTokens( token_file ):
    tokens_file = os.path.dirname(os.path.realpath(__file__)) + token_file
    if os.path.isfile(tokens_file):
        return open(tokens_file,'r').read().strip().split("\n")

def githubApiSearchCode( dork, confirm=False ):
    if confirm:
        s = 1
    else:
        s = 0.2
    time.sleep( s )
    token = random.choice( t_multi_datas['t_tokens'] )
    # token = t_multi_datas['t_tokens'][ t_multi_datas['n_current']%t_multi_datas['rate_limit'] ]
    headers = {"Authorization": "token "+token}
    # sys.stdout.write( 'progress: %d/%d\n' %  (t_multi_datas['n_current'],t_multi_datas['n_total']) )
    t_multi_datas['n_current'] = t_multi_datas['n_current'] + 1

    try:
        u = 'https://api.github.com/search/code?sort=indexed&order=desc&q='+urllib.parse.quote(dork)
        r = requests.get( u, headers=headers, timeout=5 )
        t_json = r.json()
        if 'total_count' in t_json:
            if confirm:
                return int(t_json['total_count'])
            else:
                t_results[dork] = t_json
        else:
            return False
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return False


if 'github_dorks' in t_config:
    t_results = {}
    t_multi_datas = {
        'n_current': 0,
        'n_total': len(t_config['github_dorks']),
        't_tokens': loadTokens('/.tokens'),
        'rate_limit': 30,
    }

    pool = Pool( t_config['n_multiproc'] )
    pool.map( githubApiSearchCode, t_config['github_dorks'] )
    pool.close()
    pool.join()

    # print(t_results)

    for dork,result in t_results.items():
        if type(result) is dict:
            t_config['github_dorks'][dork]['data'] = result['total_count']
            # t_new_values[dork] = {}
            # t_new_values[dork]['title'] = 'github search code \'' + dork + '\''
            # t_new_values[dork]['info'] = 'https://github.com/search?o=desc&s=indexed&type=Code&q=' + urllib.parse.quote(dork)
            # t_new_values[dork]['data'] = result['total_count']

            # if type(t_old_values[dork]) is dict and 'exclude' in t_old_values[dork]:
            #     t_new_values[dork]['exclude'] = t_old_values[dork]['exclude']


    # t_config['github_dorks'] = collections.OrderedDict( sorted(t_new_values.items()) )
    t_config['github_dorks'] = collections.OrderedDict( sorted(t_config['github_dorks'].items()) )



########### SLACK NOTIF
def sendSlackNotif( slack_webhook, message ):
    headers = {"Content-Type": "application/json"}
    t_datas = {"text": message}
    # print(json.dumps(t_datas))

    try:
        r = requests.post( slack_webhook, data=json.dumps(t_datas), headers=headers, timeout=5 )
        # print(r)
    except Exception as e:
        return


if 'slack_webhook' in t_config:
    message = ''

    for dork in t_config['github_dorks']:
        if not type(t_old_values) is dict or not dork in t_old_values or not type(t_old_values[dork]) is dict or not 'data' in t_old_values[dork]:
            old_value = -1
        else:
            old_value = int(t_old_values[dork]['data'])
        if t_config['github_dorks'][dork]['data'] != old_value:
            n_confirm = githubApiSearchCode( dork, True )
            if type(n_confirm) is int and n_confirm > old_value:
                message = message + t_config['github_dorks'][dork]['title'] + ' : ' + str(old_value) + ' -> ' + str(t_config['github_dorks'][dork]['data']) + "\n" + t_config['github_dorks'][dork]['info'] + "\n\n"

    # for key in t_new_values:
    #     if not type(t_old_values) is dict or not key in t_old_values or not type(t_old_values[key]) is dict or not 'data' in t_old_values[key]:
    #         old_value = -1
    #     else:
    #         old_value = int(t_old_values[key]['data'])
    #     if t_new_values[key]['data'] != old_value:
    #         n_confirm = githubApiSearchCode( key, True )
    #         if type(n_confirm) is int and n_confirm > old_value:
    #             message = message + t_new_values[key]['title'] + ' : ' + str(old_value) + ' -> ' + str(t_new_values[key]['data']) + "\n" + t_new_values[key]['info'] + "\n\n"


    if len(message):
        # print(message)
        message = "---------------- " + datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " ----------------\n\n" + message
        # print(message)
        sendSlackNotif( t_config['slack_webhook'], message )



########### SAVING NEW VALUES
with open(config_file, 'w') as jfile:
    json.dump( t_config, jfile, indent=4 )

