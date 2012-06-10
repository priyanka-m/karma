

*Description*

The Karma plugin fetches user activity from Bugzilla and Twitter(Uptil Now), calculates karma score and assigns badges. Users can add the karma widget to their dashboard to view their karma score, it is pre-calculated for every user.

The karma widget displays the user badge, points, number of tweets(to promote OpenSUSE) by the user and number of bugfixes on Bugzilla. Karma fetches last 50 tweets of a user and every tweet that contains OpenSUSE hashtag is rewarded with 2 karma points, and every bug fix is rewarded according to the severity of the bug, a Blocker gets 10 karma points, a Critical gets 8, Major 7 and so on so forth.

The basic backbone of the plugin is a cron script that runs daily to calculate karma score for every user on Connect.

*Admin Settings*

The plugin should be first enabled. The running the cron tab would do the needful, karma details of all users would be fetched and stored in the database.

*User settings*
Add the karma widget to your dashboard or profile to view your score and badge.
