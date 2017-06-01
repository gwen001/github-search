#!/bin/bash


function usage {
    echo "Usage: "$0" <organization> <dorks file> <token>"
    if [ -n "$1" ] ; then
		echo "Error: "$1"!"
    fi
    exit
}

if [ ! $# -eq 2 ] ; then
    usage
fi

org=$1
dorks=$2
token=$3

if [ ! -f $dorks ] ; then
    usage "dorks file not found"
fi

i=0
t_dorks="$(cat $dorks|tr ' ' '#')"
n_result=5
n_sleep=3
cnt=0

for d in $t_dorks ; do
	option="-t $token -r $n_result -o $org $(echo $d|tr '#' ' ')"
	s=$(php github-search.php $option)
	found=`echo $s | egrep -i "result\(s\) found"`
	if [ -n "$found" ] ; then
		cnt=$(expr $cnt + 1)
		echo "$s"
		echo
	fi
	sleep $n_sleep
done

echo ">>> $cnt expression found. <<<"
