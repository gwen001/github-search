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
	if [ -d $repo_name ] ; then
		cd $repo_name
		git pull
		cd ..
	else
		git clone $repo
	fi
	repo=$repo_name
fi

if [ -d $repo ] ; then
	cd $repo
	echo $(pwd)
	git pull
	cd ..
else
	echo "Directory not found!"
	exit
fi

cd $repo

for w in  ${t_keywords[@]} ; do
	git log --reflog --pretty="format:- (%H) %b" | grep --color "$w"
done
