# github-search
PHP tool to perform basic search on GitHub.  
Cookie session is mandatory if you don't provide organization name (GitHub requirement).  
Note that the search engine is case insensitive.  

```
Usage: php github-search.php [OPTIONS]

Options:
	--domain	set domain
	-h, --help	print this help
	--ip		set server ip address
	--port		set port
	--ssl		force ssl
	--threads	set maximum threads, default 1
	--wordlist	set plain text file that contains subdomains to test

Examples:
	php vhost-discover.php --domain=example.com --wordlist=sub.txt --threads=5
```

I don't believe in license.  
You can do want you want with this program.  

