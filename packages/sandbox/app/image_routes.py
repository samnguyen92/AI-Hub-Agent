"""Internal image preparation endpoints for multimodal model inspection."""

import base64
from io import BytesIO

from fastapi import APIRouter, HTTPException
from PIL import Image, ImageOps, UnidentifiedImageError
from pydantic import BaseModel

from app.path_security import resolve_workspace_path

router = APIRouter()


class PrepareImageRequest(BaseModel):
    """Workspace image path to resize for a vision model request."""

    image_path: str


@router.post("/prepare-image")
async def prepare_image(req: PrepareImageRequest) -> dict[str, str]:
    """Return a bounded JPEG data URL without exposing original image bytes."""
    image_path = resolve_workspace_path(req.image_path)
    try:
        with Image.open(image_path) as source:
            if source.width * source.height > 20_000_000:
                raise HTTPException(status_code=413, detail="Image dimensions exceed vision inspection limit")
            image = ImageOps.exif_transpose(source).convert("RGB")
            output = _encode_bounded(image)
    except (OSError, UnidentifiedImageError, Image.DecompressionBombError) as exc:
        raise HTTPException(status_code=400, detail="Workspace file is not a readable image") from exc
    encoded = base64.b64encode(output.getvalue()).decode("ascii")
    return {"data_url": f"data:image/jpeg;base64,{encoded}"}


def _encode_bounded(image: Image.Image) -> BytesIO:
    """Resize and compress large screenshots to stay below the request budget."""
    for dimension in (1280, 1024, 768):
        resized = image.copy()
        resized.thumbnail((dimension, dimension))
        output = BytesIO()
        resized.save(output, format="JPEG", quality=76, optimize=True)
        if output.tell() <= 2_000_000:
            return output
    raise HTTPException(status_code=413, detail="Image is too large for vision inspection")
