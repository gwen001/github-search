
import re
import unidecode
import itertools
from texttable import Texttable


def generatePermutations( name ):
    name = name.strip()
    if len(name) <= 0:
        return []
    t_todo = []
    altnames = []

    name = unidecode.unidecode( name ).lower()
    t_name = name.split( ' ' )
    t_todo.append( t_name )

    for n1 in t_name:
        tmp = []
        tmp.append( n1[0] )
        for n2 in t_name:
            if n1 != n2:
                tmp.append( n2 )
        t_todo.append( tmp )

    for todo in t_todo:
        t_perms = list( itertools.permutations(todo) )
        for p in t_perms:
                l = ''.join(p)
                if len(l) >= 3:
                    altnames.append( l )
                    altnames.append( re.sub('[0-9]+','',l) )
                l = '-'.join(p)
                if len(l) >= 3:
                    altnames.append( l )
                    altnames.append( re.sub('[0-9]+','',l) )

    return list( set( altnames ) )


def displayResults( t_results, t_keywords ):
    tab = Texttable( 300 )
    t_headers = ['Search title','Github profiles (repo)','Github name','Github email','Github organizations']
    if len(t_keywords):
        t_headers.append( 'Github search' )
    tab.header( t_headers )

    for employee in t_results:
        if len(employee['ghaccount']):
            str_u = ''
            str_n = ''
            str_e = ''
            str_o = ''
            str_s = ''
            if len(employee['name']):
                str_t = employee['name'] + ' - ' + employee['job'] + ' - ' + employee['company']
            else:
                str_t = employee['text']
            str_t = str_t + "\n" + employee['url']
            str_t = str_t.replace( '|', '-' )

            for login,profile in employee['ghaccount'].items():
                str_u = str_u + profile['url'] + ' (' + str(profile['repo']) + ')' + "\n"
                str_n = str_n + profile['name'] + "\n"
                str_e = str_e + profile['email'] + "\n"
                if len(profile['orgs']):
                    str_o = str_o + ",".join( profile['orgs'] )
                str_o = str_o + "\n"
                for keyword,n_results in profile['ghsearch'].items():
                    str_s = str_s + keyword+':'+str(n_results) + ','
                str_s = str_s + "\n"
                t_row = [str_t,str_u,str_n,str_e,str_o]
                if len(t_keywords):
                    t_row.append( str_s )
            
            tab.add_row( t_row )

    print( tab.draw() )
