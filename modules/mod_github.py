
import re
from modules import functions
from modules import github


def getName():
    return 'GitHub'


def getDork( term ):
    return 'site:github.com '+term


def extractPseudoFromUrl( url ):
    r = re.search( 'github.com/([a-zA-Z0-9\-]*)', url, re.IGNORECASE )
    if r:
        return r.group(1)
    else:
        return ''


def initEmployee( employee ):
    employee['tested'] = 0
    employee['ghaccount'] = {}
    employee['company'] = ''
    employee['job'] = ''
    employee['pseudo'] = extractPseudoFromUrl( employee['url'] )

    r = re.search( '[a-zA-Z0-9\-] \((.*)\) .*· GitHub', employee['text'], re.IGNORECASE )
    if r:
        employee['name'] = r.group(1).strip()
    else:
        employee['name'] = ''


def generateAltLogins( t_tokens, employee ):
    t_altlogins = [ employee['pseudo'] ]
    
    return t_altlogins
