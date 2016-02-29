Simple script to identify methods signature mismatch during inheritance while migrating to PHP7.

In PHP7 this E_STRICT error has been promoted to E_WARNING.

For comprehensive migration scanner see https://github.com/sstalle/php7cc

Usage
-----
```
php scan.php /path/to/your/sources/
```

Large projects with lots of classes will require quite a lot of memory and time to run.