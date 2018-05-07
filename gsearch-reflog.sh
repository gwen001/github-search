#!/bin/bash


t_keywords=(
	"passwd"
	"password"
	"key"
	"secret"
	"apikey"
	"api_key",
	"app_key"
	"client_secret"
	"secret_key"
	"access_key"
	"fb_secret"
	"gsecr"
	"id_rsa"
	"id_dsa"
	"amazonaws.com"
	"cloudfront.net"
)


function usage {
    echo "Usage: "$0" <repository>"
    if [ -n "$1" ] ; then
		echo "Error: "$1"!"
    fi
    exit
}

if [ ! $# -eq 1 ] ; then
    usage
fi

repo=$1
http=${repo:0:4}
repo_name=$(basename $repo)

if [ $http == "http" ] ; then
	if [ ! -d $repo_name ] ; then
		echo "Repository not found, cloning..."
		git clone $repo
	fi
	repo=$repo_name
fi

if [ -d $repo ] ; then
	echo "Repository already exists, updating..."
	cd $repo
	git pull
	cd ..
else
	echo "Something goes wrong!"
	exit
fi

cd $repo
echo "Running reflog..."
git log --reflog > reflog.txt
echo 

for w in  ${t_keywords[@]} ; do
	grep -n --color "$w" reflog.txt -A 5 -B 5
done
