
import re
from modules import functions
from modules import github


def getName():
    return 'LinkedIn'


def getDork( term ):
    return 'site:linkedin.com/in '+term


def extractPseudoFromUrl( url ):
    r = re.search( 'linkedin.com/in/([^/]*)', url, re.IGNORECASE )
    if r:
        pseudo = re.sub( '([a-zA-Z-0-9\-]+)\-[ab0123456789]{5,10}', '\\1', r.group(1) )
        return pseudo
    else:
        return ''


def initEmployee( employee ):
    employee['tested'] = 0
    employee['ghaccount'] = {}

    tmp = employee['text'].split( '-' )
    tmp[0] = re.sub( '[^a-zA-Z ]', '', tmp[0] )
    tmp[0] = re.sub( '\s+', ' ', tmp[0] )
    tmp2 = tmp[0].strip().split( ' ' )
    employee['name'] = ' '.join(tmp2[0:3]) # we don't want the permutation function runs forever

    if len(tmp) > 1:
        employee['job'] = tmp[1].strip()
    else:
        employee['job'] = ''
    
    if len(tmp) > 2:
        employee['company'] = tmp[2].strip().replace( ' | LinkedIn', '' ).strip()
    else:
        employee['company'] = ''

    employee['pseudo'] = extractPseudoFromUrl( employee['url'] )


def generateAltLogins( t_tokens, employee ):
    alt1 = functions.generatePermutations( employee['name'] )
    # print( alt1 )

    if len(employee['pseudo']):
        tmp = employee['pseudo'].split('-')
        alt2 = functions.generatePermutations( ' '.join(tmp[0:1]) )
    else:
        alt2 = []
        # print( alt2 )

    alt3 = github.githubApiSearchUser( t_tokens, employee['name'] )
    # print( alt3 )

    t_altlogins = alt1 + alt2 + alt3
    t_altlogins = list( set( t_altlogins ) )
    t_altlogins.sort()
    # print( t_altlogins )
    
    return t_altlogins
