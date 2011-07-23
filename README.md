Deployer
=======
Deployer is an open source deployment to manage syncing your codebase to your servers by automatically pulling your latest changes with the help of hooks.

Installation
=======
Setup is simple. Just clone this into the path you want and setup your hooks to point to it. The easiest is with Github, but it is not required.

For Github setup just go to your project and go to the Admin page, then choose Service Hooks and click on Post-Receive URLs.
Enter your url to deployer's php file.
Next, edit deployer's php file and change the repos how you want.

Deployer works with CDNs too that support recaching query strings.
Just point the deployer config for the repo's file to your config script and deployer will automatically replace $$GITREV$$ with the revision allowing you to
append that revision to your static files like ?revision which will tell your CDN to recache on each deployment.

Get more information at http://code.envoa.com/deployer