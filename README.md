# [SMF](https://www.simplemachines.org)
[![Build Status](https://travis-ci.org/SimpleMachines/SMF2.1.svg?branch=release-2.1)](https://travis-ci.org/SimpleMachines/SMF2.1)

This is a SMF 2.1 development repository.
The software is licensed under [BSD 3-clause license](https://opensource.org/licenses/BSD-3-Clause).

Contributions to documentation are licensed under [CC-by-SA 3](https://creativecommons.org/licenses/by-sa/3.0). Third party libraries or sets of images are under their own licenses.

## Notes:

Feel free to fork this repository and make your desired changes.

Please see the [Developer's Certificate of Origin](https://github.com/SimpleMachines/SMF2.1/blob/master/DCO.txt) in the repository:
by signing off your contributions, you acknowledge that you can and do license your submissions under the license of the project.

## Branches organization:
* ***master*** - is the main branch, only used to merge in a "final release"
* ***development*** - is the branch where the development of the "next" version/s happens
* ***release-2.1*** - is the branch where bug fixes for the version 2.1 are applied

## How to contribute:
* fork the repository. If you are not used to Github, please check out [fork a repository](https://help.github.com/fork-a-repo).
* branch your repository, to commit the desired changes.
* sign-off your commits, to acknowledge your submission under the license of the project.
  * It is enough to include in your commit comment "Signed-off by: " followed by your name and email address (for example: `Signed-off-by: Your Name <youremail@example.com>`)
  * an easy way to do so is to define an alias for the git commit command, which includes -s switch (reference: [How to create Git aliases](https://git.wiki.kernel.org/index.php/Aliases))
* send a pull request to us.

## How to submit a pull request:
* If you want to send a bug fix for the version 2.1, send it to the branch ***release-2.1***
* If you want to send a new feature, use the branch ***development***
* You should never send any pull request against the master branch
For more informations, the ideal branching we would like to follow is the one described in [this article](http://nvie.com/posts/a-successful-git-branching-model/)

Please, feel free to play around. That's what we're doing. ;)

## Security matters:

Lastly, if you have a security issue you would like to notify us about regarding SMF - not just for 2.1, but for any version -
please file a [security report](https://www.simplemachines.org/about/smf/security.php) on our website: https://www.simplemachines.org/about/smf/security.php

This will enable the team to review it and prepare patches as appropriate before exploits are widely known, which helps keep everyone safe.
