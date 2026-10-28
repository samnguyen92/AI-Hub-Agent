#!/usr/bin/env python3
"""CLI utility to verify Telegram Bot configuration and set the webhook URL.

Usage:
    python packages/api/scripts/setup_telegram.py --info
    python packages/api/scripts/setup_telegram.py --set-webhook https://your-domain.com/telegram/webhook
"""

import argparse
import asyncio
import os
import sys
import httpx

# Ensure packages/api is in sys.path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from app.config import settings


async def check_bot_info(token: str) -> None:
    print(f"Checking Telegram Bot token: {token[:6]}...{token[-4:]}")
    async with httpx.AsyncClient() as client:
        resp = await client.get(f"https://api.telegram.org/bot{token}/getMe")
        data = resp.json()
        if data.get("ok"):
            res = data["result"]
            print("✅ Bot is active and valid!")
            print(f"   Name:     {res.get('first_name')}")
            print(f"   Username: @{res.get('username')}")
            print(f"   ID:       {res.get('id')}")
            print(f"   Link:     https://t.me/{res.get('username')}")
        else:
            print(f"❌ Failed to get bot info: {data.get('description')}")


async def set_webhook(token: str, url: str, secret: str = "") -> None:
    print(f"Setting webhook to: {url}")
    payload = {"url": url}
    if secret:
        payload["secret_token"] = secret

    async with httpx.AsyncClient() as client:
        resp = await client.post(f"https://api.telegram.org/bot{token}/setWebhook", json=payload)
        data = resp.json()
        if data.get("ok"):
            print("✅ Webhook set successfully!")
            print(f"   Description: {data.get('description')}")
        else:
            print(f"❌ Failed to set webhook: {data.get('description')}")


async def get_webhook_info(token: str) -> None:
    async with httpx.AsyncClient() as client:
        resp = await client.get(f"https://api.telegram.org/bot{token}/getWebhookInfo")
        data = resp.json()
        if data.get("ok"):
            res = data["result"]
            print("Current Webhook Info:")
            print(f"   URL:                  {res.get('url')}")
            print(f"   Has custom cert:      {res.get('has_custom_certificate')}")
            print(f"   Pending update count: {res.get('pending_update_count')}")
            print(f"   Last error message:   {res.get('last_error_message') or 'None'}")
        else:
            print(f"❌ Failed to get webhook info: {data.get('description')}")


def main():
    parser = argparse.ArgumentParser(description="Telegram Bot setup utility for AI Hub Agent")
    parser.add_argument("--token", help="Override TELEGRAM_BOT_TOKEN")
    parser.add_argument("--info", action="store_true", help="Print bot and webhook info")
    parser.add_argument("--set-webhook", help="Webhook public URL (e.g. https://domain.com/telegram/webhook)")
    parser.add_argument("--secret", help="Webhook secret token")
    args = parser.parse_args()

    token = args.token or getattr(settings, "telegram_bot_token", None) or os.getenv("TELEGRAM_BOT_TOKEN")
    if not token or token == "dummy":
        print("❌ Error: TELEGRAM_BOT_TOKEN is not configured in .env or passed via --token.")
        sys.exit(1)

    secret = args.secret or getattr(settings, "telegram_webhook_secret", None) or os.getenv("TELEGRAM_WEBHOOK_SECRET") or ""

    if args.set_webhook:
        asyncio.run(set_webhook(token, args.set_webhook, secret))
        asyncio.run(get_webhook_info(token))
    else:
        asyncio.run(check_bot_info(token))
        asyncio.run(get_webhook_info(token))


if __name__ == "__main__":
    main()
