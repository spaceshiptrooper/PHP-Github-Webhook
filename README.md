# PHP Github Webhook

## Overview

This small script allows you to use your Github repo so that you can update your website using your Github repo. What this script does is once you update a file on your Github repo, it'll actually download the updated file onto your server where you specified the `destination` to be downloaded to. In order for this to work, you must add the `deploy.php` link to your Github repo settings.

---

## Setting up this Webhook

1. Go to the Github repo where you want to set this webhook on.
2. Once in the Github repo, you should have 7 tabs at the top.
	- Code
	- Issues
	- Pull requests
	- Projects
	- Wiki
	- Insights
	- Settings
3. Click on `Settings`.
4. Once you are in the `Settings` tab, there should be 6 tabs and 1 moderation tab.
	- Options
	- Collaborators
	- Branches
	- Webhooks
	- Integrations & services
	- Deploy keys
5. Click on `Webhooks`.
6. Once in the `Webhooks` section, at the top right hand corner you should see a `Add webhook` button. Click on it.
7. Once clicked, you should now be in a section where you have to configure your Github webhook.
8. Only add the full URL to your webhook to the `Payload URL` text field.
9. Scroll down and click on the green `Add webhook` button.

You only need to add the full URL of your webhook to this section. Don't change anything else. This should allow Github to only trigger and access your webhook URL once you push (update) a file on your Github repo.

![](https://i.imgur.com/Ida9AQt.png)
![](https://i.imgur.com/kotvymQ.png)
![](https://i.imgur.com/5dnp397.png)
![](https://i.imgur.com/Vo4EJdT.png)
![](https://i.imgur.com/zPgtKgK.png)

---

## NOTE

Just a little reminder, you have to use a legitimate webhook URL or this won't work. Make sure you provide a working URL. In the pictures, we use `index.php?url=deploy` because for some cases, you may only want to allow a certain action to be triggered if the `$_GET` parameters equals a certain action. In this case, if you were to access just `index.php`, the webhook will *not* be triggered since it doesn't contain the `$_GET` parameter `deploy`. What ever you put in the webhook URL **has** to pertain to your situation and use. Don't just copy exactly like the pictures if you didn't setup your files like that.

---

## Webhook sample code

To initiate the `webhook`, we have to actually include it in our code and call the `Deploy` class. To do so, we'll need to understand what it requires. Here is a sample code of what it may require.

```
use \Github\Webhook\Deploy;

// Change this path appropriately
require_once '/PATH/TO/THE/DEPLOY/FILE/deploy.php';
require_once '/PATH/TO/THE/CONFIG/FILE/config.php'; // Make sure that this file is outside of the public folder

$deploy = new Deploy([
	'github' => [ // Required only if you are running a private Github repo
		'username' => GITHUB_USERNAME,
		'password' => GITHUB_PASSWORD,
		'repo' => [
			'JUST_THE_REPO' // Something like spaceshiptrooper/PHP-Github-Webhook
		],
		'branch' => 'master'
	],
	'options' => [
		'destination' => 'PATH_TO_WHERE_THE_UPDATED_FILE_WILL_BE_STORED'
	]
]);

print $deploy->deploy();
```

Then create a file called `config.php` outside of the public folder. This file **should not** be accessible for anyone. Basically, you **should not** be able to see this file if you were to look for it in your website via a web browser. You should only have access to it via a file manager. Next, create `constants` within that `config.php` file and use the below sample code to setup your `Github` credentials.

```
define('GITHUB_USERNAME', 'YOUR_GITHUB_USERNAME_HERE');
define('GITHUB_PASSWORD', 'YOUR_GITHUB_PASSWORD_HERE');
```

If you are running a `private` Github repo, **you are required to use the github array**. This is because since the Github repo is a private repository, you need access to that repo. You **cannot** download a private repo if you don't have access to that repo. If you do not use the `github` username and password array for private repos and you attempt to run the deploy file, you'll end up getting a 404 error message because again, you need permission to that repo. This is because `Github` requires you to have permission to access that repo. If you don't, you will get a 404 error message. If you are running a `public` Github repo, the `github` array doesn't apply to you. Your sample code *may* only look like this.

```
// FOR PUBLIC GITHUB REPOS ONLY
use \Github\Webhook\Deploy;

// Change this path appropriately
require_once '/PATH/TO/THE/DEPLOY/FILE/deploy.php';

$deploy = new Deploy([
	'github' => [
		'repo' => [
			'JUST_THE_REPO' // Something like spaceshiptrooper/PHP-Github-Webhook
		],
		'branch' => 'master'
	],
	'options' => [
		'destination' => 'PATH_TO_WHERE_THE_UPDATED_FILE_WILL_BE_STORED'
	]
]);

print $deploy->deploy();
```

---

## NOTE

Just a side note. Since `Github` is a little clunky at automatically determining what kind of repository your repo may be whether it's a public or private repo, the messages in the payload **will not** change until you make a new `push` event or send a new payload. For instance, going from private to public. If you attempted to run the `deploy` file when you're running a private repo without the `Github` username and password, you will get a 404 error. If you tried to change that repo from private to public, you will still receive that 404 error message if you redelivered that payload. You will keep seeing the 404 error message until you send a new payload. Not entirely sure if that's just how `Github` was designed, but this will be the problem if you run into it. The same goes for going from public to private. You have to send a new payload to `Github` in order to avoid this problem.

---

## Debugging

If you really must and there is no way of getting the correct payload, just create a file called `github.json` and place it in the same directory as the `deploy.php` file. Next, copy and paste the desired payload into the `github.json` file. Next, you'll need to add the debug option in the `options` array. Something like so

```
'options' => [
	'destination' => 'PATH_TO_WHERE_THE_UPDATED_FILE_WILL_BE_STORED',
	'debug' => true
]
```

This will allow you to bypass the `POST` request checker and from there, you can access that payload's result directly. This should allow you to debug and figure out what is going on.

Just a little remember though. This is just for debugging purposes and not for intentional use. Please use this option cautiously because this option has **not** been implemented correctly.

---

If there is any problem using this webhook, please feel free to submit an [issue](https://github.com/spaceshiptrooper/PHP-Github-Webhook/issues).