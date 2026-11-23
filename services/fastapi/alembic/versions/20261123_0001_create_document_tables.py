"""Create document and chunk tables.

Revision ID: 20261123_0001
Revises:
Create Date: 2026-11-23
"""

from typing import Sequence, Union

import os

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = "20261123_0001"
down_revision: Union[str, Sequence[str], None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Create pgvector extension and document storage tables."""
    dimensions = int(os.getenv("EMBEDDING_DIMENSIONS", "1536"))
    if dimensions <= 0:
        raise ValueError("EMBEDDING_DIMENSIONS must be a positive integer")

    op.execute(sa.text("CREATE EXTENSION IF NOT EXISTS vector"))
    op.execute(
        sa.text(
            """
            CREATE TABLE IF NOT EXISTS documents (
                id uuid PRIMARY KEY,
                filename text NOT NULL,
                content_type text,
                file_hash text NOT NULL UNIQUE,
                storage_path text NOT NULL,
                status text NOT NULL DEFAULT 'processing',
                error_message text,
                chunk_count integer NOT NULL DEFAULT 0,
                created_at timestamptz NOT NULL DEFAULT now()
            )
            """
        )
    )
    op.execute(
        sa.text(
            f"""
            CREATE TABLE IF NOT EXISTS document_chunks (
                id bigserial PRIMARY KEY,
                document_id uuid NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
                chunk_index integer NOT NULL,
                content text NOT NULL,
                page_number integer,
                section_title text,
                metadata jsonb NOT NULL DEFAULT '{{}}'::jsonb,
                embedding vector({dimensions}) NOT NULL,
                search_vector tsvector GENERATED ALWAYS AS (
                    to_tsvector('simple', coalesce(section_title, '') || ' ' || content)
                ) STORED,
                UNIQUE(document_id, chunk_index)
            )
            """
        )
    )
    op.execute(
        sa.text(
            "CREATE INDEX IF NOT EXISTS chunks_document_id_idx "
            "ON document_chunks(document_id)"
        )
    )
    op.execute(
        sa.text(
            "CREATE INDEX IF NOT EXISTS chunks_search_vector_idx "
            "ON document_chunks USING gin(search_vector)"
        )
    )
    op.execute(
        sa.text(
            "CREATE INDEX IF NOT EXISTS chunks_embedding_hnsw_idx "
            "ON document_chunks USING hnsw (embedding vector_cosine_ops)"
        )
    )


def downgrade() -> None:
    """Drop document storage tables."""
    op.execute(sa.text("DROP TABLE IF EXISTS document_chunks"))
    op.execute(sa.text("DROP TABLE IF EXISTS documents"))
