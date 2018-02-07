# github-search
PHP tool to perform basic search on GitHub.  
Cookie session is mandatory if you don't provide organization name (GitHub requirement).  
Note that the search engine is case insensitive.  

```
Usage: php github-search.php [OPTIONS]

Options:
	-c	set cookie session
	-e	file extension filter
	-f	looking for file
	-h	print this help
	-l	language filter
	-n	no color
	-o	provide organization name
	-r	maximum number of results, default 100
	-s	search string
	-t	set authorization token (overwrite cookie)

Examples:
	php github-search.php -o myorganization -s db_password
	php github-search.php -o myorganization -f wp-config.php -s db_password
	php github-search.php -c "user_session=B0KqycP8LlYORc-s3WFZoH71TG" -f wp-config -e php -r 1000
	php github-search.php -t 32a11e6f340c2fe1a6071795a3b1a8c876b3cf29 -l php -s DB_USERNAME
```

<img src="https://raw.githubusercontent.com/gwen001/github-search/master/example.png" alt="GitHub search example">
<br><br>

I don't believe in license.  
You can do want you want with this program.  

