"""initial_schema

Revision ID: 940ff8aa28cd
Revises: 
Create Date: 2026-10-09 01:55:46.128702

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from app.db.session import Base
import app.db.models  # noqa: F401 - register all models
import app.db.reference_models  # noqa: F401 - register design references


# revision identifiers, used by Alembic.
revision: str = '940ff8aa28cd'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create the initial application schema from registered SQLAlchemy models."""
    Base.metadata.create_all(bind=op.get_bind())


def downgrade() -> None:
    """Drop the application schema created by this revision."""
    Base.metadata.drop_all(bind=op.get_bind())
