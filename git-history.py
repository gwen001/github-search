#!/usr/bin/python2

# I don't believe in license.
# You can do whatever you want with this program.

import os
import sys
import json
import re
import argparse
import random
import subprocess
import time
from os.path import expanduser
from colored import fg, bg, attr
from multiprocessing.dummy import Pool
# from pynput import keyboard
import keyboard

# for i in range(0,256):
#     sys.stdout.write( '%s[%d] Hello world.%s\n' %  (fg(i),i,attr(0)) )
# exit()


def on_press( key ):
    if key.name == 'q' or key.name == 'esc':
        t_stats['getout'] = True
    if key.name == 'r':
        t_stats['skip_repo'] = True
    if key.name == 'c':
        t_stats['skip_commit'] = True
    if key.name == 'e':
        t_stats['skip_regexp'] = True

# keyboard.on_press(on_press)


parser = argparse.ArgumentParser()
parser.add_argument( "-p","--path",help="path to scan" )
parser.add_argument( "-d","--date",help="do no check commits prior this date, format: YYYY-MM-DD" )
parser.add_argument( "-c","--length",help="only check in first n characters" )
parser.add_argument( "-r","--regexp",help="regexp to search (can be a tomnomnom gf file)" )
parser.add_argument( "-t","--threads",help="max threads, default: 10" )
parser.parse_args()
args = parser.parse_args()

if args.path:
    path = args.path
    if not os.path.isdir(path):
        parser.error( 'path not found' )
else:
    parser.error( 'path is missing' )

if args.threads:
    max_threads = int(args.threads)
else:
    max_threads = 10

if args.date:
    max_date = int( time.mktime(time.strptime(args.date,'%Y-%m-%d')) )
    str_max_date = args.date
else:
    # no limit
    max_date = -1
    str_max_date = '-1 (no limit)'
    # max_date = '2018-01-01 00:00:00'

if args.length:
    max_length = int(args.length)
    str_max_length = args.length+' chars'
else:
    # no limit
    max_length = -1
    str_max_length = '-1 (no limit)'
    # max_length = 1000

t_regexp = []
if args.regexp:
    if os.path.isfile(args.regexp):
        sys.stdout.write( '%s[+] loading regexp: %s%s\n' %  (fg('green'),args.regexp,attr(0)) )
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
    parser.error( 'regexp is missing' )

l_regexp = len(t_regexp)
if not l_regexp:
    parser.error( 'regexp is missing' )

t_regexp_compiled = []
for regexp in t_regexp:
    t_regexp_compiled.append( re.compile(r'(.{0,50})('+regexp+')(.{0,50})') )

sys.stdout.write( '%s[+] %d regexp found.%s\n' %  (fg('green'),l_regexp,attr(0)) )
print( "\n".join(t_regexp) )
sys.stdout.write( '%s[+] scanning directory: %s%s\n' %  (fg('green'),path,attr(0)) )

output = subprocess.check_output( "find "+path+" -type d -name '.git'", shell=True ).decode('utf-8')
t_repo = output.strip().split("\n")
l_repo = len(t_repo)
sys.stdout.write( '%s[+] %d repositories found.%s\n' %  (fg('green'),l_repo,attr(0)) )
sys.stdout.write( '%s[+] options are ->  max_threads: %d, max_date: %s, max_length: %s%s\n' %  (fg('green'),max_threads,str_max_date,str_max_length,attr(0)) )


def doCheckCommit( commit ):
    t_stats['skip_commit'] = False
    t_stats['skip_regexp'] = False

    if t_stats['getout'] or t_stats['skip_repo']:
        return

    sys.stdout.write( 'progress: %d/%d\r' %  (t_stats['n_current'],t_stats['n_commit']) )
    sys.stdout.flush()
    t_stats['n_current'] = t_stats['n_current'] + 1

    if t_stats['max_date'] > -1 and int(commit['date']) < int(t_stats['max_date']):
        # print('skip %s %s %s\n' % (commit['commit'], commit['date'], t_stats['max_date']) )
        return

    try:
        original_content = subprocess.check_output( 'cd "'+t_stats['repo']+'"; git show '+commit['commit']+' 2>&1', shell=True )
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        return

    try:
        content = original_content.decode('utf-8').strip()
    except Exception as e:
        content = original_content.strip()

    if t_stats['max_length']:
        content = content[0:max_length]

    # for regexp in t_regexp:
    for regexp in t_regexp_compiled:
        if t_stats['getout'] or t_stats['skip_repo']:
            # print('R')
            break
        if t_stats['skip_commit']:
            # print('C')
            t_stats['skip_commit'] = False
            break
        if t_stats['skip_regexp']:
            # print('E')
            t_stats['skip_regexp'] = False
            continue

        r = re.findall( regexp, content )
        # r = re.findall( '(.{0,50})('+regexp+')(.{0,50})', content )
        # print(regexp)
        if r:
            for rr in r:
                if not rr[1] in t_stats['t_findings']:
                    t_stats['t_findings'].append( rr[1] )
                    str = commit['commit'] +' : ' + rr[0].lstrip() + ('%s%s%s'%(fg('light_red'),rr[1],attr(0))) + rr[-1].rstrip()
                    sys.stdout.write( '%s\n' % str )

t_stats = {
    'getout': False,
    'skip_repo': False,
    'skip_commit': False,
    'skip_regexp': False
}

for repo in t_repo:
    if t_stats['getout']:
        exit()

    repo = repo.replace('.git','')
    sys.stdout.write( '%s[+] %s%s\n' %  (fg('cyan'),repo,attr(0)) )

    try:
        output = subprocess.check_output( "cd "+repo+"; git log --pretty=format:'{\"commit\":\"%H\",\"date\":\"%at\"}' 2>&1", shell=True ).decode('utf-8')
    except Exception as e:
        sys.stdout.write( "%s[-] error occurred: %s%s\n" % (fg('red'),e,attr(0)) )
        continue

    t_commit = json.loads('['+output.replace('\n',',')+']')
    # print(t_commit)

    # t_stats = {
    #     'max_date': max_date,
    #     'max_length': max_length,
    #     'n_current': 0,
    #     'n_commit': len(t_commit),
    #     'repo': repo,
    #     't_findings': []
    # }
    t_stats['skip_repo'] = False
    t_stats['skip_commit'] = False
    t_stats['skip_regexp'] = False
    t_stats['max_date'] = max_date
    t_stats['max_length'] = max_length
    t_stats['n_current'] = 0
    t_stats['n_commit'] = len(t_commit)
    t_stats['repo'] = repo
    t_stats['t_findings'] = []

    pool = Pool( max_threads )
    pool.map( doCheckCommit, t_commit )
    pool.close()
    pool.join()

