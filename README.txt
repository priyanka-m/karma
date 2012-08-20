

*Description*

The Karma plugin fetches user activity from Bugzilla, Twitter and Planet openSUSE, OBS, openSUSE Wiki, calculates karma score and assigns badges. Users can add the karma widget to their dashboard to view their karma score, it is pre-calculated for every user. Karma allows you to extend your kudos to others and award points to them. User rank is also calculated by karma on the basis of total karma score to numerically distinguish the best from the rest.

The karma widget displays the user badge, points, number of tweets(to promote OpenSUSE) by the user, number of bugfixes, number of posts on planet openSUSE, number of build service commits and number of wiki entries. Karma fetches last tweets of a user and every tweet that is meant to promote openSUSE is rewarded with 2 karma points, and every bug fix is rewarded according to the severity of the bug, a Blocker gets 10 karma points, a Critical gets 8, Major 7 and so on so forth. All those users whose blog feeds are aggregated on planet openSUSE and are actively posting are also rewarded. Every commit is awarded 5 points and every Wiki edit is awarded 2 points.

The basic backbone of the plugin is a cron script that runs every 5 minutes to calculate karma score for every user on Connect.

*Admin Settings*

The plugin should be first enabled. Then running the cron tab would do the needful, karma details of all users would be fetched and stored in the database.

*User settings*
Add the karma widget to your dashboard or profile to view your score and badge.

*Further Details*
For a detailed description of the Karma plugin, visit en.opensuse.org/Karma to view the Documentation of the plugin.
