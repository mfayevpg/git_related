git_related
===========

This a collection of scripts to do some simple git tasks.

Installing the Scripts
----------------------
Those scripts are meant to be run from the command line.
Best practice would be to clone this repo in some dir then make a symbolic link to each script in your /usr/local/bin
`git git@github.com:mfayevpg/git_related.git git_related
chmod 755 git_related/tester_branch/tester_branch_switch.php
sudo ln -s git_related/tester_branch/tester_branch_switch.php /usr/local/bin/tester_branch`
This way you can call them from wherever you are.
`mfaye@vp-aix-dev11:/nfs-dev/FRANCE-VPG/mfaye/www/bg_builder$tester_branch 33116`
Any explanation on how to use those scripts in this document will assume that you have done this operation

missing_merge
-------------
When developpements are running on more than one cycle, it can be easy to miss some merges.
The job of this script is to give you a convenient way to spot them.
1. First get to your project on redmine and extract all the issues and put all the issues id in a 
"redmine_point_list.txt" file.
2. Go to your console and run the following command : 
`check project_branch application [redmine_point_list_file]

project_branch          : The branch you want to check for missing merge (typically S49_911 for instance)
                          Be advised that your local repository needs to be on this branch, 
                          otherwise an error will be issued
application             : Application that needs to be check [bong, front, oxy]
redmine_point_list_file : Path to the file that contents the list of the redmine project`
