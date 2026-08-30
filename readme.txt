========================================================================
SippyGo - Water Bottle Online Shopping Web
System Testing & Credentials Guide
========================================================================


1. LOCAL SERVER URLS

------------------------------------------------------------------------

This project uses PHP's built-in development server.

Start the server from the project root folder:

    php -S localhost:8000

Local Server Base URL:
    http://localhost:8000/

Login Page:
    http://localhost:8000/login.php

Admin Dashboard:
    http://localhost:8000/admin_dashboard.php

NOTE:
The port number may be different depending on the local PHP server
configuration. If another port is used, replace 8000 in the URLs above.

Example:

    php -S localhost:9000

The new base URL would be:

    http://localhost:9000/


2. TEST CREDENTIALS

------------------------------------------------------------------------

[ ADMIN ACCOUNTS ]

Account 1:

- Email:       daniel@example.com
- Password:    Daniel@123
- Role:        admin
- Target URL:  http://localhost:8000/admin_dashboard.php


Account 2:

- Email:       admin@example.com
- Password:    admin123
- Role:        admin
- Target URL:  http://localhost:8000/admin_dashboard.php

[ MEMBER ACCOUNTS ]

Account 1 (John):

- Email:          john@example.com
- Password:       john123
- Role:            member
- Reward Points:  500 points


Account 2 (Jane):

- Email:          jane@example.com
- Password:       jane123
- Role:            member
- Reward Points:  250 points


Account 3 (Ali):

- Email:          ali@example.com
- Password:       ali123
- Role:            member
- Reward Points:  0 points


3. DATABASE SETUP

------------------------------------------------------------------------

1. Open phpMyAdmin or another MySQL client.

2. Import the database schema file:

   db/database.sql

3. The SQL file automatically creates the database:

   waterbottle_shop

4. Make sure your MySQL server is running.

5. Make sure the database connection settings in the project
   configuration match your local MySQL setup.




4. RUNNING THE SYSTEM

------------------------------------------------------------------------

1. Open the project folder in Visual Studio Code.

2. Start the PHP development server from the project root folder:

   php -S localhost:8000

3. Open the following URL in a browser:

   http://localhost:8000/

========================================================================
END OF README
========================================================================