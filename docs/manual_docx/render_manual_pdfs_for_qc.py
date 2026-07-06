from pathlib import Path

import pypdfium2 as pdfium
from PIL import Image, ImageDraw, ImageFont


BASE_DIR = Path(__file__).resolve().parent
PDFS = {
    "english": BASE_DIR / "SCAIMS_User_Manual_English.pdf",
    "tagalog": BASE_DIR / "SCAIMS_User_Manual_Tagalog.pdf",
}


def get_font(size=22):
    for candidate in (
        "C:/Windows/Fonts/calibri.ttf",
        "C:/Windows/Fonts/arial.ttf",
    ):
        path = Path(candidate)
        if path.exists():
            return ImageFont.truetype(str(path), size)
    return ImageFont.load_default()


def render_pdf(pdf_path, out_dir, scale=1.55):
    out_dir.mkdir(parents=True, exist_ok=True)
    for old_page in out_dir.glob("page_*.png"):
        try:
            old_page.unlink()
        except PermissionError:
            pass
    pdf = pdfium.PdfDocument(str(pdf_path))
    pages = []
    for index in range(len(pdf)):
        page = pdf[index]
        bitmap = page.render(scale=scale)
        image = bitmap.to_pil().convert("RGB")
        path = out_dir / f"page_{index + 1:03d}.png"
        image.save(path, quality=92)
        pages.append(path)
    return pages


def make_contact_sheet(page_paths, output_path, title):
    font = get_font(24)
    label_font = get_font(20)
    thumb_width = 420
    margin = 24
    label_height = 34
    columns = 2

    thumbnails = []
    for path in page_paths:
        image = Image.open(path).convert("RGB")
        ratio = thumb_width / image.width
        thumb_height = int(image.height * ratio)
        thumb = image.resize((thumb_width, thumb_height), Image.LANCZOS)
        thumbnails.append((path, thumb))

    max_thumb_height = max(thumb.height for _, thumb in thumbnails)
    title_height = 58
    rows = (len(thumbnails) + columns - 1) // columns
    sheet_width = margin + columns * thumb_width + (columns - 1) * margin + margin
    sheet_height = title_height + rows * (max_thumb_height + label_height + margin) + margin
    sheet = Image.new("RGB", (sheet_width, sheet_height), "white")
    draw = ImageDraw.Draw(sheet)
    draw.text((margin, 16), title, fill=(31, 77, 120), font=font)

    for idx, (path, thumb) in enumerate(thumbnails):
        row = idx // columns
        col = idx % columns
        x = margin + col * (thumb_width + margin)
        y = title_height + row * (max_thumb_height + label_height + margin)
        draw.rectangle(
            (x - 1, y - 1, x + thumb_width + 1, y + thumb.height + 1),
            outline=(190, 200, 210),
            width=1,
        )
        sheet.paste(thumb, (x, y))
        draw.text((x, y + max_thumb_height + 5), path.stem.replace("_", " ").title(), fill=(60, 70, 80), font=label_font)

    sheet.save(output_path, quality=92)


def main():
    for label, pdf_path in PDFS.items():
        if not pdf_path.exists():
            raise FileNotFoundError(pdf_path)
        out_dir = BASE_DIR / f"qc_rendered_final_{label}"
        pages = render_pdf(pdf_path, out_dir)
        make_contact_sheet(
            pages,
            BASE_DIR / f"_qc_contact_sheet_{label}.png",
            f"SCAIMS Manual {label.title()} - {len(pages)} rendered pages",
        )
        print(f"{label}: {len(pages)} pages")


if __name__ == "__main__":
    main()
