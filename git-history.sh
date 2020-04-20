#!/bin/bash

# Inspired by @Tomnomnom
# https://twitter.com/tomnomnom/status/1133345832688857095

# git-history.sh <keyword>

keyword=$1

# if you also use his gf tool, you can prefix the keyword: 'k_<keyword>'
# https://github.com/tomnomnom/gf
if [ ${keyword:0:2} == "k_" ] ; then
	k=${keyword:2}
	kf=~/.gf/$k.json
	if [ -f $kf ] ; then
		tmp=`cat $kf | egrep '"patterns"'`
		if [ -n "$tmp" ] ; then
			t_keywords=(`cat $kf | jq '.patterns | .[]'`)
		else
			t_keywords=(`cat $kf | jq '.pattern'`)
		fi
	fi
else
	t_keywords="#$keyword#"
fi

n_keywords=${#t_keywords[@]}
i=0

echo "#### KEYWORDS: $n_keywords"
for (( i=0; i<$n_keywords; i++ )) ; do
	keyword=${t_keywords[$i]:1:-1}
	keyword=`echo $keyword | sed "s/\\\\\\\\\\\\\/\\\\\/g"`
	t_keywords[$i]=$keyword
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

	t_idx=$(find .git/objects/pack/ -name "*.idx")
	echo $t_idx

	for idx in $t_idx ; do
		if [ $fname -eq 1 ] ; then
			echo $idx
		fi
		
		# git show-index < "$idx"
		t_obj=$(git show-index < "$idx" | awk '{print $2}')
		# echo $t_obj

		for obj in $t_obj ; do
			if [ $fname -eq 1 ] ; then
				echo $obj
			fi
			
			# content=$(git cat-file -p $obj)
			# in order to save time, search only occurs on first 1M
			content=$(git cat-file -p $obj | head -c1000000)
			
			# size=$(echo $content | wc -c)
			# echo $size
			# if [ $size -gt 1500000 ] ; then
			# 	# if size < 1.5M
			# 	continue
			# fi

			for (( i=0; i<$n_keywords; i++ )) ; do
				keyword=${t_keywords[$i]}
				# echo $keyword

				echo $content | egrep --color -Ioa ".{0,50}$keyword.{0,50}"
				# git cat-file -p $obj | egrep --color -oa ".{0,50}$keyword.{0,50}"

				# {
				# 	find .git/objects/pack/ -name "*.idx" |
				# 	while read i ; do
				# 		# if [ $fname -eq 1 ] ; then
				# 		# 	echo $i
				# 		# fi
				# 		git show-index < "$i" | awk '{print $2}';
				# 	done;

				# 	find .git/objects/ -type f | grep -av '/pack/' | awk -F '/' '{print $(NF-1)$NF}';
				# } | while read o ; do
				# 	if [ $n -ge 5 ] ; then
				# 		break
				# 	fi
				# 	n=$((n+1))
				# 	if [ $fname -eq 1 ] ; then
				# 		echo $o
				# 	fi
				# 	git cat-file -p $o | egrep --color -oa ".{0,50}$keyword.{0,50}"
				# 	break
				# done

			done
		done
	done

	echo
	cd $pwd
done
