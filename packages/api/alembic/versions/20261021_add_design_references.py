"""Create project-scoped design reference storage."""

from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

revision: str = "20261021_refs"
down_revision: Union[str, None] = "c4617da47736"
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create the reference table when it is not already present."""
    inspector = sa.inspect(op.get_bind())
    if "design_references" in inspector.get_table_names():
        return
    op.create_table(
        "design_references",
        sa.Column("id", sa.String(length=36), primary_key=True),
        sa.Column("project_id", sa.String(length=36), sa.ForeignKey("projects.id", ondelete="CASCADE"), nullable=False),
        sa.Column("source_url", sa.String(length=1000), nullable=False),
        sa.Column("title", sa.String(length=200), nullable=False),
        sa.Column("notes", sa.Text(), nullable=True),
        sa.Column("tags", postgresql.JSONB(astext_type=sa.Text()), server_default=sa.text("'[]'::jsonb"), nullable=False),
        sa.Column("desktop_path", sa.String(length=500), nullable=False),
        sa.Column("mobile_path", sa.String(length=500), nullable=False),
        sa.Column("snapshot_path", sa.String(length=500), nullable=False),
        sa.Column("page_summary", sa.Text(), nullable=True),
        sa.Column("captured_at", sa.DateTime(timezone=True), nullable=False),
    )
    op.create_index("ix_design_references_project_id", "design_references", ["project_id"])


def downgrade() -> None:
    """Drop the project reference table."""
    inspector = sa.inspect(op.get_bind())
    if "design_references" in inspector.get_table_names():
        op.drop_index("ix_design_references_project_id", table_name="design_references")
        op.drop_table("design_references")
