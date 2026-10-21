"""Script to clean up test and demo projects from database and workspaces."""

import asyncio
import os
import sys

# Ensure api root is in sys.path
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))

from sqlalchemy import delete, select
from app.db.models import Project, User
from app.db.session import async_session_maker
from app.services.project_service import project_service


async def main() -> None:
    print("Starting test and demo data cleanup...")
    async with async_session_maker() as db:
        res = await project_service.cleanup_demo_projects(db)
        print(f"Purged {res['deleted_count']} demo/test projects.")

        # Also purge orphan test users
        u_res = await db.execute(
            select(User).where(
                User.github_login.like("gate_user_%")
                | User.github_login.like("test_user_%")
                | User.github_login.like("test_%")
                | User.github_login.like("owner_%")
                | User.github_login.like("other_%")
            )
        )
        test_users = u_res.scalars().all()
        for u in test_users:
            await db.execute(delete(User).where(User.id == u.id))
        await db.commit()
        print(f"Purged {len(test_users)} test users.")

    print("Cleanup completed successfully.")


if __name__ == "__main__":
    asyncio.run(main())
