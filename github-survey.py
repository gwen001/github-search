#!/usr/bin/python3.5

import os
import sys
import re
import time
import json
import requests
import random
import datetime
import urllib.parse
from pathlib import Path
from colored import fg, bg, attr
from multiprocessing.dummy import Pool



########### LOAD CONFIG
config_file = os.path.dirname(os.path.realpath(__file__)) + '/github-survey.json'

try:
    with open(config_file) as jfile:
        t_config = json.load( jfile )
except Exception as e:
    sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
    exit()

if 'github_dorks' in t_config:
    t_old_values = t_config['github_dorks']
else:
    t_old_values = {}

t_new_values = {}



########### GITHUB SEARCH CODE
def loadTokens( token_file ):
    tokens_file = os.path.dirname(os.path.realpath(__file__)) + token_file
    if os.path.isfile(tokens_file):
        return open(tokens_file,'r').read().strip().split("\n")

def githubApiSearchCode( dork ):
    time.sleep( 0.1 )
    token = random.choice( t_multi_datas['t_tokens'] )
    # token = t_multi_datas['t_tokens'][ t_multi_datas['n_current']%t_multi_datas['rate_limit'] ]
    headers = {"Authorization": "token "+token}
    # sys.stdout.write( 'progress: %d/%d\n' %  (t_multi_datas['n_current'],t_multi_datas['n_total']) )
    t_multi_datas['n_current'] = t_multi_datas['n_current'] + 1

    try:
        r = requests.get( 'https://api.github.com/search/code?sort=indexed&order=desc&q='+urllib.parse.quote(dork), headers=headers, timeout=5 )
        t_json = r.json()
        if 'total_count' in t_json:
            t_results[dork] = t_json
            # t_results[dork] = t_json['total_count']
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

    pool = Pool( 5 )
    pool.map( githubApiSearchCode, t_config['github_dorks'] )
    pool.close()
    pool.join()

    # print(t_results)

    for dork,result in t_results.items():
        if type(result) is dict:
            t_new_values[dork] = {}
            t_new_values[dork]['title'] = 'github search code "' + dork + '"'
            t_new_values[dork]['info'] = 'https://github.com/search?o=desc&s=indexed&type=Code&q=' + urllib.parse.quote(dork)
            t_new_values[dork]['data'] = result['total_count']


    t_config['github_dorks'] = t_new_values



########### SAVING NEW VALUES
with open(config_file, 'w') as jfile:
    json.dump( t_config, jfile, indent=4 )



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

    for key in t_new_values:
        if not type(t_old_values) is dict or not key in t_old_values or not type(t_old_values[key]) is dict or not 'data' in t_old_values[key]:
            old_value = "<empty>"
        else:
            old_value = t_old_values[key]['data']
        if old_value != t_new_values[key]['data']:
            message = message + t_new_values[key]['title'] + ' : ' + str(old_value) + ' -> ' + str(t_new_values[key]['data']) + "\n" + t_new_values[key]['info'] + "\n\n"

    if len(message):
        message = "---------------- " + datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " ----------------\n\n" + message
        # print(message)
        sendSlackNotif( t_config['slack_webhook'], message )
