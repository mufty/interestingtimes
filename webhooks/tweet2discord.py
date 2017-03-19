#!/usr/bin/env python

import json
import os
import requests
import time

# depends on https://github.com/bear/python-twitter
import twitter

# Windows console bugfix. for unicode handling
import win_unicode_console
win_unicode_console.enable()


def getSettings():
    with open('tweet2discord.conf', 'r') as s:
        settings = json.load(s)
        s.close()
        return(settings)

def doLogin (settings):
        api = twitter.Api(consumer_key=settings['consumer_key'],
                      consumer_secret=settings['consumer_secret'],
                      access_token_key=settings['access_token_key'],
                      access_token_secret=settings['access_token_secret'])
        return(api)

def getID(json):
    try:
        return int(json.id)
    except KeyError:
        return 0

def getTweets(account, twitter):
    filteredTweets = []
    statuses = twitter.GetUserTimeline(screen_name=account, exclude_replies=True)
    # Need to reverse sort the API results so oldest tweet ID is first
    statuses.sort(key=getID, reverse=False)
    # Check so we don't get any replies
    for tweet in statuses:
        if (tweet.text.startswith( '@' ) is False):
            filteredTweets.append(tweet)
    return(filteredTweets)

def checkIfPosted(settings,tweet):
    tid = ([tweet.id])
    name = ([tweet.user.screen_name])
    for acc in settings['account']:
        if (acc['name'] == name[0]):
            # Convert the ID's to ints for comparison.
            temp_id = acc['last']
            temp_tid = map(int, tid)
            # If it's greater than we stored, it's new tweet
            if (int(temp_tid[0]) > int(temp_id)):
                return(True)
            else:
                return(False)

def postToWebhook(settings,tweet):
    username = tweet.user.name
    avatar_url = tweet.user.profile_image_url
    content = ("tweeted at " + str(tweet.created_at) + "\n"
            + "https://twitter.com/" + str(tweet.user.screen_name) + "/status/" + str(tweet.id))
    payload = {"username": username, "avatar_url": avatar_url, "content":content}
    webhook_url = settings['webhook_url']
    # Do the post - no error checking because yolo!
    response = requests.post(
                webhook_url, data=json.dumps(payload),
                headers={'Content-Type': 'application/json'}
                )
    # Don't spam it
    time.sleep(2)

def updatePostCount(name, newid, settings):
    with open('tweet2discord.conf', 'w') as s:
        for acc in settings['account']:
            if (acc['name'] == name):
                acc['last'] = newid
                s.write(json.dumps(settings, sort_keys=True))
    s.close()


def main():
    tweets = []
    # Get settings and do twitter login
    settings = getSettings()
    tw = doLogin(settings)
    # Get the latest tweets per defined account
    for acc in settings['account']:
        tweets = getTweets(acc['name'], tw)
        for twi in tweets:
            # Check if it's a new tweet
            if (checkIfPosted(settings, twi)):
                postToWebhook(settings, twi)
                updatePostCount(acc['name'], twi.id_str, settings)


if __name__ == "__main__":
    main()
