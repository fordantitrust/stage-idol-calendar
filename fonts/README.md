# Fonts directory

Place TrueType/OpenType font files here for the server-side image generator (`image.php`).

> **Shared hosting users**: Upload font files directly here — no server admin access needed.

---

## 1. Main font (Thai + Latin)

`image.php` auto-detects the first file found. Pick **one** Thai font:

| Font | Files needed | Size | Download |
|------|-------------|------|----------|
| **Noto Sans Thai** | `NotoSansThai-Regular.ttf`, `NotoSansThai-Bold.ttf` | ~200 KB each | https://fonts.google.com/noto/specimen/Noto+Sans+Thai |
| **Sarabun** | `Sarabun-Regular.ttf`, `Sarabun-Bold.ttf` | ~100 KB each | https://fonts.google.com/specimen/Sarabun |
| **Prompt** | `Prompt-Regular.ttf`, `Prompt-Bold.ttf` | ~100 KB each | https://fonts.google.com/specimen/Prompt |
| **Kanit** | `Kanit-Regular.ttf`, `Kanit-Bold.ttf` | ~100 KB each | https://fonts.google.com/specimen/Kanit |

1. Download the ZIP from Google Fonts
2. Extract and copy the `.ttf` files into this directory
3. `image.php` will auto-detect and use the first font found

---

## 2. Symbol + Japanese font (recommended)

**GNU Unifont** covers the entire Unicode BMP in a single file — including Thai-adjacent symbols
(♾ ★ ✓ ⌚), CJK punctuation (【】「」『』), and **all Japanese scripts**
(Hiragana, Katakana, common Kanji).

| Font | File | Size | Coverage | Download |
|------|------|------|----------|----------|
| **GNU Unifont** | `unifont.ttf` | ~12 MB | ★ Symbols + Japanese + most BMP scripts | https://unifoundry.com/unifont/ |

> **This one file handles both Section 3 (symbols) and Section 4 (Japanese).**
> Most shared hosting users only need this + a Thai font (Section 1).

---

## 3. Symbol-only fallback (lighter alternative to Unifont)

Covers BMP symbols (♾ ★ ✓ ⌚) and Mathematical Alphanumeric characters
(𝗕𝗔𝗖𝗞 𝗜𝗡 𝗧𝗜𝗠𝗘 → rendered as BACK IN TIME), but **not Japanese**.

| Font | File | Size | Coverage | Download |
|------|------|------|----------|----------|
| **Symbola** | `Symbola.ttf` | ~1 MB | Wide symbol coverage; **no Japanese** | https://dn-works.com/ufas/ |

Use Symbola if you don't need Japanese and want to save disk space.

---

## 4. Dedicated Japanese font (higher quality than Unifont)

Optional. Provides better-quality Japanese rendering than Unifont.

| Font | File | Size | Coverage | Download |
|------|------|------|----------|----------|
| **Noto Sans JP** (static) | `NotoSansJP-Regular.ttf` | ~4 MB | ★ Japanese — best quality | See note below |
| **Noto Sans CJK** | `NotoSansCJK-Regular.otf` | ~16 MB | Japanese + Chinese + Korean | https://github.com/notofonts/noto-cjk/releases |

> **⚠️ Important — Download the static version of Noto Sans JP:**
>
> Google Fonts now distributes Noto Sans JP as a **variable font** (~5–6 MB).
> Variable fonts may not work on shared hosting servers with older FreeType libraries.
>
> To get the static version:
> 1. Go to https://fonts.google.com/noto/specimen/Noto+Sans+JP
> 2. Click **"Download family"**
> 3. Open the ZIP → go into the **`static/`** subfolder
> 4. Copy `NotoSansJP-Regular.ttf` (~4 MB) into this directory
>
> If only the root-level `NotoSansJP-Regular.ttf` (~5–6 MB) is available,
> use **GNU Unifont** instead — it works on all shared hosting environments.

---

## Per-character font routing

`image.php` routes each character to the correct font automatically:

| Character type | Font used |
|---|---|
| Thai / Latin | Main font (Section 1) |
| Japanese (Hiragana, Katakana, Kanji), CJK punctuation (【】「」) | Dedicated Japanese font → Unifont → system fonts |
| Symbols (♾ ★ ✓ ⌚) | Unifont / Symbola → system fonts |

Each font is verified at startup with a differential pixel test before being selected.

---

## Recommended setup for shared hosting

Minimum (Thai only):
```
NotoSansThai-Regular.ttf   ~200 KB
NotoSansThai-Bold.ttf      ~200 KB
```

Recommended (Thai + Japanese + symbols):
```
NotoSansThai-Regular.ttf   ~200 KB
NotoSansThai-Bold.ttf      ~200 KB
unifont.ttf                ~12 MB
```

Best quality (Thai + Japanese):
```
NotoSansThai-Regular.ttf   ~200 KB
NotoSansThai-Bold.ttf      ~200 KB
NotoSansJP-Regular.ttf     ~4 MB   ← static version from static/ subfolder
Symbola.ttf                ~1 MB   ← for symbols (♾ ★ etc.)
```

---

## Linux / VPS (root access)

```bash
# Thai font
apt-get install -y fonts-thai-tlwg

# Japanese / CJK font
apt-get install -y fonts-noto-cjk

# Symbol + Japanese fallback
apt-get install -y fonts-unifont

fc-cache -fv
```

## Docker

```dockerfile
RUN apt-get update && apt-get install -y \
    fonts-thai-tlwg \
    fonts-noto-cjk \
    fonts-unifont \
    && fc-cache -fv \
    && rm -rf /var/lib/apt/lists/*
```

Or copy font files directly (no package install needed):

```dockerfile
COPY fonts/NotoSansThai-Regular.ttf /app/fonts/
COPY fonts/NotoSansThai-Bold.ttf    /app/fonts/
COPY fonts/unifont.ttf              /app/fonts/
```
