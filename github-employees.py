#!/usr/bin/python3

# I don't believe in license.
# You can do whatever you want with this program.

import os
import sys
import time
import json
import re
import argparse
from termcolor import colored
from goop import goop
from modules import functions
from modules import github
from multiprocessing.dummy import Pool
from lockfile import LockFile


#
# Handling command line arguments
#
parser = argparse.ArgumentParser()
parser.add_argument( "-m","--mod",help="module to use to search employees on google, available: linkedin, github, default: github" )
parser.add_argument( "-s","--startpage",help="search start page, default 0" )
parser.add_argument( "-f","--fbcookie",help="your facebook cookie" )
parser.add_argument( "-t","--term",help="term (usually company name)", action="append" )
parser.add_argument( "-p","--page",help="n page to grab, default 10" )
parser.add_argument( "-r","--resume",help="resume previous session" )
parser.add_argument( "-i","--input",help="input datas source saved from previous search" )
parser.add_argument( "-k","--keyword",help="github keyword to search" )
parser.add_argument( "-o","--token",help="github token" )
parser.parse_args()
args = parser.parse_args()

if args.startpage:
    start_page = int(args.startpage)
else:
    start_page = 0

if args.page:
    n_page = int(args.page)
else:
    n_page = 10

if args.fbcookie:
    fb_cookie = args.fbcookie
else:
    fb_cookie = os.getenv('FACEBOOK_COOKIE')
if not fb_cookie:
    parser.error( 'facebook cookie is missing' )

if args.term:
    t_terms = args.term.split(',')
else:
    # t_terms = []
    if not args.input:
        parser.error( 'term is missing' )

if args.keyword:
    t_keywords = args.keyword.split(',')
    if not args.token:
        parser.error( 'token is missing' )
    else:
        t_tokens = args.token.split(',')
else:
    t_keywords = []


t_tokens = []

if args.token:
    t_tokens = args.token.split(',')
else:
    tokens_file = os.path.dirname(os.path.realpath(__file__))+'/.tokens'
    if os.path.isfile(tokens_file):
        fp = open(tokens_file,'r')
        for line in fp:
            r = re.search( '^([a-f0-9]{40})$', line )
            if r:
                t_tokens.append( r.group(1) )

if not len(t_tokens):
    parser.error( 'auth token is missing' )

if args.input:
    f_input = args.input
else:
    f_input = ''

if args.resume:
    f_resume = args.resume
else:
    f_resume = ''

if args.mod:
    mod = args.mod
else:
    mod = 'github'

if mod == 'github':
    from modules import mod_github as mod
elif mod == 'linkedin':
    from modules import mod_linkedin as mod
else:
    parser.error( 'module not found' )
#####


#
# Perform search
#
t_results = []
t_history = []
end_page = start_page + n_page
f_search_result = 'gh_search_'+str(int(time.time()))
f_progress = 'gh_progress_'+str(int(time.time()))
gg_history = {}


#
# Multi processing functions
#
def doMultiSearch( page ):
    zero_result = 0
    for i in range(page-5,page-1):
        if i != page and i in gg_history and gg_history[i] == 0:
            zero_result = zero_result + 1

    if zero_result < 3:
        s_results = goop.search( gg_search, fb_cookie, page=page )
        sys.stdout.write( '[+] grabbing page %d/%d... (%d)\n' %  (page,end_page,len(s_results)) )
        gg_history[page] = len(s_results)
        # print(s_results)

        for i in s_results:
            pseudo = mod.extractPseudoFromUrl( s_results[i]['url'] )
            if len(pseudo) and not pseudo in t_history:
                t_history.append( pseudo )
                t_results.append( s_results[i] )
    else:
        for i in range(page,end_page):
            gg_history[i] = 0
#####


if f_input:
    # load search results from file
    sys.stdout.write( colored('[+] loading search results from file: %s\n' %  f_input, 'green') )
    with open(f_input) as json_file:
        t_results = json.load( json_file )
elif f_resume:
    # load datas from previous sessions
    sys.stdout.write( colored('[+] loading datas from previous sessions: %s\n' %  f_resume, 'green') )
    with open(f_resume) as json_file:
        t_results = json.load( json_file )
else:
    # perform search on Google
    for term in t_terms:
        sys.stdout.write( colored('[+] looking for employees on %s: %s\n' %  (mod.getName(),term.upper()), 'green') )
        gg_search = mod.getDork( term )
        pool = Pool( 5 )
        pool.map( doMultiSearch, range(0,end_page) )
        pool.close()
        pool.join()

        # for i in range(start_page,end_page):
        #     sys.stdout.write( '[+] grabbing page %d/%d... ' %  ((i+1),end_page) )
        #     s_results = goop.search( gg_search, fb_cookie, page=i )
        #     sys.stdout.write( '(%d)\n' %  len(s_results) )
            
        #     for i in s_results:
        #         pseudo = mod.extractPseudoFromUrl( s_results[i]['url'] )
        #         if len(pseudo) and not pseudo in t_history:
        #             t_history.append( pseudo )
        #             t_results.append( s_results[i] )
    
    if f_search_result:
        # save search results
        sys.stdout.write( colored('[+] save search results: %s\n' %  f_search_result, 'green') )
        with open(f_search_result, 'w') as json_file:
            json.dump(t_results, json_file)

n_results = len(t_results)
sys.stdout.write( colored('[+] %d employees found.\n' % n_results , 'green') )
#####


#
# Generate alternative logins
#
t_stats = {
    'counter':0,
    'n_altlogins':0,
    'n_ghaccount': 0,
}
n_start = 0

def doMultiGenerateLogins( employee ):
    # if (t_stats['counter']%10) == 0:
        # show progress
    sys.stdout.write( 'progress: %d/%d\r' %  (t_stats['counter'],n_results) )
        # sys.stdout.flush()
    t_stats['counter'] = t_stats['counter'] + 1

    mod.initEmployee( employee )
    employee['altlogins'] = mod.generateAltLogins( t_tokens, employee )
    t_stats['n_altlogins'] = t_stats['n_altlogins'] + len(employee['altlogins'])

if f_resume:
    # load datas from previous sessions
    f_progress = f_resume
    for i in range(0,n_results):
        t_stats['n_altlogins'] = t_stats['n_altlogins'] + len(t_results[i]['altlogins'])
        t_stats['n_ghaccount'] = t_stats['n_ghaccount'] + len(t_results[i]['ghaccount'])
        if t_results[i]['tested'] == 1:
            n_start = n_start + 1
    sys.stdout.write( '[+] resume starts at index: %d\n' % n_start  )
else:
    # generate alternative logins
    n = 0
    sys.stdout.write( '[+] generating logins...\n'  )
    t_stats['counter'] = 0
    pool = Pool( 5 )
    pool.map( doMultiGenerateLogins, t_results )
    pool.close()
    pool.join()

    # for employee in t_results:
    #     if (n%50) == 0:
    #         # show progress
    #         sys.stdout.write( 'progress: %d/%d\n' %  (n,n_results) )
    #     n = n + 1

    #     mod.initEmployee( employee )
    #     employee['altlogins'] = mod.generateAltLogins( t_tokens, employee )

    #     t_stats['n_altlogins'] = t_stats['n_altlogins'] + len(employee['altlogins'])

sys.stdout.write( colored('[+] %d alternative logins created.\n' %  t_stats['n_altlogins'], 'green') )
sys.stdout.write( '[+] testing logins on Github...\n' )
sys.stdout.write( '[+] datas file: %s\n' %  f_progress )
#####


#
# Test all logins on GitHub
#
t_stats['counter'] = n_start - 1
lock = LockFile('./ghemp')

def doMultiTestLogins( index ):
    employee = t_results[index]
    # if t_stats['n_ghaccount'] >= 5:
    #     break
    
    for login in employee['altlogins']:
        time.sleep( 200/1000 )
        t_stats['counter'] = t_stats['counter'] + 1

        if f_progress and not lock.is_locked():
            try:
                lock.acquire()
                with open(f_progress,'w') as json_file:
                    json.dump( t_results, json_file )
                lock.release()
            except Exception as e:
                a = 1

        url = 'https://github.com/'+login

        # if (t_stats['counter']%5) == 0 and f_progress:
            # save progress
        # if (t_stats['counter']%100) == 0:
            # show progress
            # sys.stdout.write( 'progress: %d/%d\n' %  (t_stats['counter'],t_stats['n_altlogins']) )
        sys.stdout.write( 'progress: %d/%d\r' %  (t_stats['counter'],t_stats['n_altlogins']) )
        
        if len(t_tokens):
            newghaccount = github.grabUserApi( t_tokens, login )
        else:
            newghaccount = github.grabUserHtml( login )

        if newghaccount == False:
            # something bad happened, let's have little break por favor
            time.sleep( 2 )
            continue

        if len(newghaccount) and newghaccount['repo'] > 0:
            github.grabUserHtmlLight( newghaccount, login ) # let's try to grab more about the organization
            t_stats['n_ghaccount'] = t_stats['n_ghaccount'] + 1
            # sys.stdout.write( colored('%s\n' %  url, 'cyan') )

            if len(t_keywords):
                for keyword in t_keywords:
                    newghaccount['ghsearch'][keyword] = github.githubApiSearchCode( t_tokens, login, keyword )
            
            employee['ghaccount'][login] = newghaccount
        # else:
            # sys.stdout.write( colored('%s\n' %  url, 'white') )

    employee['tested'] = 1

pool = Pool( 10 )
pool.map( doMultiTestLogins, range(n_start,n_results) )
pool.close()
pool.join()

# for i in range(n_start,n_results):
    # employee = t_results[i]
    # # if t_stats['n_ghaccount'] >= 5:
    # #     break
    
    # for login in employee['altlogins']:
    #     time.sleep( 200/1000 )
    #     t_stats['counter'] = t_stats['counter'] + 1

    #     if (t_stats['counter']%5) == 0 and f_progress:
    #         # save progress
    #         with open(f_progress, 'w') as json_file:
    #             json.dump(t_results, json_file)
    #     if (t_stats['counter']%100) == 0:
    #         # show progress
    #         sys.stdout.write( 'progress: %d/%d\n' %  (t_stats['counter'],t_stats['n_altlogins']) )
        
    #     url = 'https://github.com/'+login

    #     if len(t_tokens):
    #         newghaccount = github.grabUserApi( t_tokens, login )
    #     else:
    #         newghaccount = github.grabUserHtml( login )

    #     if newghaccount == False:
    #         # something bad happened, let's have little break por favor
    #         time.sleep( 2 )
    #         continue

    #     if len(newghaccount) and newghaccount['repo'] > 0:
    #         github.grabUserHtmlLight( newghaccount, login ) # let's try to grab more about the organization
    #         t_stats['n_ghaccount'] = t_stats['n_ghaccount'] + 1
    #         sys.stdout.write( colored('%s\n' %  url, 'cyan') )

    #         if len(t_keywords):
    #             for keyword in t_keywords:
    #                 newghaccount['ghsearch'][keyword] = github.githubApiSearchCode( t_tokens, login, keyword )
            
    #         employee['ghaccount'][login] = newghaccount
    #     else:
    #         sys.stdout.write( colored('%s\n' %  url, 'white') )

    # employee['tested'] = 1
#####


#
# Display results
#
sys.stdout.write( colored('[+] %d profiles found.\n' %  t_stats['n_ghaccount'], 'green') )

functions.displayResults( t_results, t_keywords )
#####


#
# Save
#
if lock.is_locked():
    lock.release()

if f_progress:
    with open(f_progress, 'w') as json_file:
        json.dump(t_results, json_file)

sys.stdout.write( colored('[+] datas saved: %s\n\n' %  f_progress, 'green') )
#####
