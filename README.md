Easy mint Alternative: Web App
==============================

EasyMalt is a pretty simple (visually) alternative to mint.com  
It is a web-app that displays aggregated banking information from the various institutions you work with.

_You don't need to share your banking credentials (nor should you!)._ 
Using the [Local Downloader & Importer](https://github.com/gboudreau/easymalt-local), you'll keep your banking credentials [secure on your own computer](https://pypi.python.org/pypi/keyring#what-is-python-keyring-lib).  
Daily, the Downloader will run on your computer, using your stored credentials, and download all the new transactions from the various websites you have access to (banks, credit cards, etc.)  
The imported transactions & accounts data can then be sent to your web server, to be stored there. The web-app reads that database, and gives you access to your data, to do as you please. Categorize, tag, and rename transactions. Automatically or manually.  

But that is not all... Pie charts!!1

<img width="614" alt="image" src="https://github.com/gboudreau/easymalt/assets/370329/a53e3889-f0f9-42e4-a0d0-4b42316af191">

Requirements
------------

- A web host that can run PHP (PHP 7 is recommended, but it should work on the latest 5.6.x too), and connect to a MySQL database.
- A MySQL database (!)

Installation
------------

1. Create and initialize the database to save the data into:
    ```
    mysql > CREATE DATABASE 'easymalt' CHARACTER SET = 'utf8mb4';
    mysql > GRANT SELECT, UPDATE, INSERT, DELETE ON 'easymalt'.* to 'loc_fin_user'@'localhost' identified by 'some_password_here';
    mysql > USE easymalt;
    mysql > source _dbschema/schema.sql
    ```

  - Change the available categories in the `categories` table.
  - Change the available tags in the `tags` table.
  - Add/change/delete the example post-processing rules from the `post_processing` table.

2. Copy `config.example.php` to `config.php`, and change the configuration options in that file.

3. Copy everything to your web server, and configure your web daemon to handle index.php files as the default files.  
   You can find sample configuration for nginx in the _/_server_example_config/_ folder.  
   I __strongly__ suggest using HTTPS ([Let's Encrypt](https://letsencrypt.org/) is nice), and configuring a htaccess password to protect your installation.

4. Install the [Local Downloader & Importer](https://github.com/gboudreau/easymalt-local), and configure it to send its data to your web-app (it needs the URL to the _/api/_ folder, and the token you configured in _config.php_).
