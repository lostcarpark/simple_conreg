
DESCRIPTION
-----------

Simple Convention Registration is a module to allow 

REQUIREMENTS
------------

 * A Stripe account and the Stripe library (downloaded during install).
   You can register a Stripe account at https://www,stripe.com
 * If you wish to send Email updates to members, Simplenews is required.


INSTALLATION
------------

 1. CREATE DIRECTORY

    Create a new directory "simple_conreg" in the sites/all/modules directory and
    place the entire contents of this simple_conreg folder in it.

 2. RUN COMPOSER

    From the Drupal base directory, run the commands:

    php sites/all/modules/composer_manager/scripts/init.php
    composer dumpautoload
    composer drupal-update

    This is required to install the Stripe library.

 3. ENABLE THE MODULE

    Enable the module on the Modules admin page.

 4. ACCESS PERMISSION

    Grant the access at the Access control page:
      People > Permissions.

 5. CONFIGURE SIMPLE CONVENTION REGISTRATION

    Configure Simple Convention Registration on the admin pages:
      Configuration > Simple Convention Registration.

    Ensure you enter your private and public Stripe keys.

 6. LINK TO SIMPLENEWS

    If you wish to send email newsletters to your convention members, install
    Simplenews, and configure a newsletter. Enable the newsletter for Simple
    Convention Registration and select what classes of members should be added.


RELATED MODULES
------------

 * Simplenews
   Allows mailing lists to be set up.
   http://http://drupal.org/project/simplenews


DOCUMENTATION
-------------
More help can be found on the help pages: http://simple_conreg.lostcarpark.com
and in the drupal.org handbook: 
