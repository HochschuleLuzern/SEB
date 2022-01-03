##########################################################################################################################################################################
# ILIAS 5.4 is EOL and so is this version of the plugin. There will be no more Updates or Bugfixes for it. Please move to ILIAS 7 and the current version of the plugin. #
##########################################################################################################################################################################

# SEB

The ILIAS SEB plugin is a UIHook-Plugin for ILIAS that improves how ILIAS integrates with the [Safe Exam Browser](http://safeexambrowser.org). It has the following features:
* It reduces the ILIAS interface removing the main menu, the breadcrumb, the menu in the upper right, the tab-menu, the link to the startpage through the logo, the left and the right columns on the personal desktop and the right column in the repository.
* It implements a template that is clearly different from a standard ILIAS template.
* It allows to block users having certain roles from accessing the ILIAS installation with any other browser, either through sitewide keys or object specific keys. Object specific keys only allow access to the ILIAS object (test) they are defined in. **Be aware that sitewide keys might pose a risk for your exam if using them in BYOD exams!**
* Object-specific keys can be deactivated (for backward compatibility).
* It adds a menu to change the language without having to access the user settings to the interface.
* It adds a tab for administrators to the test object (could be expanded to other objects in the future) to add object specific keys.
* If "Prevent Simultaneous Logins" is activated a tab for session management in the test object can be activated, allowing to delete sessions for specific test users, to allow users back in if they are locked out due to a browser or computer issue (exiting SEB without closing the session).

This plugin is a completely refactored fork of the SEB plugin developed by Stefan Schneider at the University of Marburg.


**Minimum ILIAS Version:**
5.2.0

**Maximum ILIAS Version:**
5.3.999

**Responsible Developer:**
Stephan Winiker - stephan.winiker@hslu.ch

**Supported Languages:**
German, English

### Quick Installation Guide
1. Copy the content of this folder in <ILIAS_directory>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB or clon this Github-Repo to <ILIAS_directory>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/

2. Access ILIAS, go to the administration menu and select "Plugins".

3. Look for the SEB-Plugin in the table, press the "Actions" button and select "Update".

4. Press the "Actions" button and select "Configure" to choose your settings. Be aware that you might be locking out users from your installation, so check the bylines of the different options for the correct settings.

5. Press the "Actions" button and select "Activate" to activate the plugin.

6. If you want to use object specific keys and have activated the corresponding option, users with write rights on a test can set the keys directly in the corresponding tab in the test object. This key is only valid for this test, so a direct static link to the test object (to be found in the info tab) MUST be set in the SEB config file. It is possible to show an User Agreement after login.
