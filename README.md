# PhpBB Extension - toxyy Anonymous Posts v0.4.0

[Topic on phpBB.com](https://www.phpbb.com/community/viewtopic.php?f=456&t=2488071)

## Requirements

phpBB 3.2.4-RC1+ PHP 7+ (ticket 15819)
martti's Group Template Variables (https://www.phpbb.com/community/viewtopic.php?f=456&t=2325391)

## Features

Post anonymously in topics as a user.  All user information is hidden from users, except to staff, who have a special view.

All places the former username would appear are replaced, including the topic review, forumrow, topicrow, and
postrow, even in search.php pages.  Quotes are supported as well.

Users will receive notifications to their anonymous posts. (IN DEV)

Forum and user (including group) permissions are available in the ACP.  Permit individual groups
and users to be able to post anonymously, in whatever forums you've permitted to allow
anonymous posts.

Turkish language supported, multiple styles soon to be supported.

## Screenshot

https://i.snag.gy/J6qsbE.jpg

https://i.snag.gy/jfS8NP.jpg

https://i.snag.gy/esmnia.jpg

https://i.snag.gy/XnmsLf.jpg

https://i.snag.gy/A6Bd7g.jpg

https://i.snag.gy/bsftYz.jpg

https://i.snag.gy/3Z84rf.jpg

https://i.snag.gy/qrMX6B.jpg

## Quick Install

You can install this on the latest release of phpBB 3.2 by following the steps below:

* Create `toxyy/anonymousposts` in the `ext` directory.
* Download and unpack the repository into `ext/toxyy/anonymousposts`
* Enable `Anonymous Posts` in the ACP at `Customise -> Manage extensions`.

## Uninstall

* Disable `Anonymous Posts` in the ACP at `Customise -> Extension Management -> Extensions`.
* To permanently uninstall, click `Delete Data`. Optionally delete the `/ext/toxyy/anonymousposts` directory.

## Support

* Report bugs and other issues to the [Issue Tracker](https://github.com/toxyy/anonymousposts/issues).

## License

[GPL-2.0](license.txt)