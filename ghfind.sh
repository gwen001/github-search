#!/bin/bash

# Inspired by @Tomnomnom
# https://twitter.com/tomnomnom/status/1133345832688857095

# ghfind.sh <keyword>

keyword=$1

# if you also his gf tool, you can prefix the keyword: 'k_<keyword>'
# https://github.com/tomnomnom/gf
if [ ${keyword:0:2} == "k_" ] ; then
	k=${keyword:2}
	kf=~/.gf/$k.json
	if [ -f $kf ] ; then
		tmp=`cat $kf | grep '"patterns"'`
		if [ -n "$tmp" ] ; then
			t_keywords=(`cat $kf | jq '.patterns | .[]'`)
		else
			t_keywords=(`cat $kf | jq '.pattern'`)
		fi
	fi
else
	t_keywords=$keyword
fi

n_keywords=${#t_keywords[@]}
i=0

echo "#### KEYWORDS: $n_keywords"
# exit
for (( i=0; i<$n_keywords; i++ )) ; do
	# echo $t_keywords[$i]
	echo ${t_keywords[$i]}
done
echo "####"
echo

# exit

lrepo=$(find . -type d -name .git)
pwd=$(pwd)
if [ $# -gt 1 ] ; then
	fname=1
else
	fname=0
fi

for r in $lrepo ; do
	i=0
	rr=$(dirname $r)
	echo ">>>> $rr <<<<"
	echo
	cd $rr

	for (( i=0; i<$n_keywords; i++ )) ; do
		keyword=${t_keywords[$i]}
		# echo $keyword
		keyword=${keyword:1:-1}
		# keyword=`echo $keyword | tr -d '\\\'`
		keyword=`echo $keyword | sed "s/\\\\\\\\\\\\\/\\\\\/g"`
		# echo $keyword

		{
			find .git/objects/pack/ -name "*.idx" |
			while read i ; do
				if [ $fname -eq 1 ] ; then
					echo $i
				fi
				git show-index < "$i" | awk '{print $2}';
			done;

			find .git/objects/ -type f | grep -av '/pack/' | awk -F '/' '{print $(NF-1)$NF}';
		} | while read o ; do
			if [ $fname -eq 1 ] ; then
				echo $o
			fi
			git cat-file -p $o | egrep --color -ai "$keyword"
		done


	done

	echo
	cd $pwd
done
