
import re
import requests
import random
from termcolor import colored


def githubApiGetUser( t_tokens, login ):
    headers = {"Authorization": "token "+random.choice(t_tokens)}
    
    try:
        r = requests.get( 'https://api.github.com/users/'+login, headers=headers, timeout=5 )
        t_user = r.json()
        if 'login' in t_user and t_user['login'] == login:
            return t_user
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )

    return False


def githubApiSearchUser( t_tokens, name ):
    t_users = []
    headers = {"Authorization": "token "+random.choice(t_tokens)}
    
    try:
        r = requests.get( 'https://api.github.com/search/users?q='+name.replace(' ','+'), headers=headers, timeout=5 )
        json = r.json()
        if 'total_count' in json and json['total_count'] > 0:
            for e in json['items']:
                t_users.append( e['login'] )
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )

    return t_users


def githubApiSearchCode( t_tokens, user, keyword ):
    headers = {"Authorization": "token "+random.choice(t_tokens)}
    
    try:
        r = requests.get( 'https://api.github.com/search/code?q=user%3A'+user+'+'+keyword, headers=headers, timeout=5 )
        json = r.json()
        if 'total_count' in json:
            return json['total_count']
        else:
            return 0
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return 0


def grabUserApi( t_tokens, login ):
    user = githubApiGetUser( t_tokens, login )
    # print( user )
    t_return = {}

    if user and user['public_repos'] > 0:
        t_return['ghsearch'] = {}
        t_return['url'] = user['html_url']
        t_return['repo'] = user['public_repos']
        if user['name'] is None:
            t_return['name'] = ''
        else:
            t_return['name'] = user['name']
        if user['company'] is None:
            t_return['orgs'] = []
        else:
            t_return['orgs'] = [ user['company'].lower()[:20] ]
        if user['email'] is None:
            t_return['email'] = ''
        else:
            t_return['email'] = user['email']

    return t_return


def grabUserHtml( login ):
    url = 'https://github.com/'+login

    try:
        r = requests.get( url, timeout=5 )
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return False

    t_return = {}

    if not 'Not Found' in r.text:
        r_repo = re.search( 'tab=repositories">\s*Repositories\s*<span class="Counter hide-lg hide-md hide-sm">\s*([0-9]*)\s*</span>', r.text, re.MULTILINE|re.IGNORECASE )

        if r_repo and int(r_repo.group(1)) > 0:
            t_return['ghsearch'] = {}
            t_return['url'] = url
            t_return['repo'] = int(r_repo.group(1))
            t_return['orgs'] = []

            r_name = re.search( 'itemprop="name">([^<]*)</span>', r.text, re.MULTILINE|re.IGNORECASE )
            if r_name:
                t_return['name'] = r_name.group(1)
            else:
                t_return['name'] = ''
            
            r_email = re.search( 'aria-label="Email: ([^"]*)"', r.text, re.MULTILINE|re.IGNORECASE )
            if r_email:
                t_return['email'] = r_email.group(1)
            else:
                t_return['email'] = ''
            
            r_org = re.search( 'data-hovercard-url="/orgs/([^/]*)/hovercard"', r.text, re.MULTILINE|re.IGNORECASE )
            if r_org:
                o = r_org.group(1).lower()[:20]
                if o not in ghaccount['orgs']:
                    ghaccount['orgs'].append( o )
            
            r_org = re.search( 'aria-label="Organization: ([^"]*)"', r.text, re.MULTILINE|re.IGNORECASE )
            if r_org:
                o = r_org.group(1).lower()[:20]
                if o not in ghaccount['orgs']:
                    ghaccount['orgs'].append( o )

            r_status = re.search( 'class="user-status-message-wrapper f6 mt-1 text-gray-dark ws-normal lh-condensed">\s*<div>.* at ([^<]*)', r.text, re.MULTILINE|re.IGNORECASE )
            if r_status and r_status.group(1).lower() not in ghaccount['orgs']:
                o = r_status.group(1).lower()[:20]
                if o not in ghaccount['orgs']:
                    ghaccount['orgs'].append( o )

            r_bio = re.search( 'js-user-profile-bio"><div>.* at ([^<]*)', r.text, re.MULTILINE|re.IGNORECASE )
            if r_bio and r_bio.group(1).lower() not in ghaccount['orgs']:
                o = r_bio.group(1).lower()[:20]
                if o not in ghaccount['orgs']:
                    ghaccount['orgs'].append( o )

    return t_return


def grabUserHtmlLight( ghaccount, login ):
    url = 'https://github.com/'+login

    try:
        r = requests.get( url, timeout=5 )
    except Exception as e:
        print( colored("[-] error occurred: %s" % e, 'red') )
        return False

    if not 'Not Found' in r.text:
        r_org = re.search( 'data-hovercard-url="/orgs/([^/]*)/hovercard"', r.text, re.MULTILINE|re.IGNORECASE )
        if r_org:
            o = r_org.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )
        
        r_org = re.search( 'aria-label="Organization: ([^"]*)"', r.text, re.MULTILINE|re.IGNORECASE )
        if r_org:
            o = r_org.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )

        r_status = re.search( 'class="user-status-message-wrapper f6 mt-1 text-gray-dark ws-normal lh-condensed">\s*<div>.* at ([^<]*)', r.text, re.MULTILINE|re.IGNORECASE )
        if r_status and r_status.group(1).lower() not in ghaccount['orgs']:
            o = r_status.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )

        r_bio = re.search( 'js-user-profile-bio"><div>.* at ([^<]*)', r.text, re.MULTILINE|re.IGNORECASE )
        if r_bio and r_bio.group(1).lower() not in ghaccount['orgs']:
            o = r_bio.group(1).lower()[:20]
            if o not in ghaccount['orgs']:
                ghaccount['orgs'].append( o )
