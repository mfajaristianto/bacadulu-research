# Arsip Dokumentasi

Berisi dokumentasi lama yang **tidak lagi menjadi sumber aturan utama**. Isi unik sudah dikonsolidasikan ke dokumen aktif di [docs/](../README.md). Berkas di sini dipindahkan dengan `git mv` (riwayat Git terjaga) dan **tidak diubah isinya**.

Bila arsip bertentangan dengan dokumen aktif, **dokumen aktif yang berlaku**. Contoh konflik yang diketahui: token warna `#F26B4F`/`#17172B` dan font Plus Jakarta Sans pada arsip vs standar baru di [DESIGN_SYSTEM.md](../DESIGN_SYSTEM.md).

## Isi

| Berkas | Asal | Dikonsolidasikan ke |
|---|---|---|
| `bacadulu-context/` (README, 00–10, `CONTEXT_MANIFEST.json`) | `docs/bacadulu-context/` | PRD, DESIGN_SYSTEM, ARCHITECTURE, SECURITY, DEPLOYMENT, TESTING, FRONTEND_RESOURCES |
| `bacadulu-context/00_MASTER_CONTEXT_ALL_IN_ONE.md` | Gabungan 01–09 | Sama seperti di atas |
| `bacadulu-context/10_ALL_PREVIOUS_READMES_VERBATIM.md` | Gabungan README lama (termasuk `BACARESEARCH_IMPLEMENTATION_STATUS.md` yang berkas aslinya sudah tidak ada) | Riwayat |
| `DESIGN_BACADULU_RESEARCH_V3.md` | Root | DESIGN_SYSTEM, FRONTEND_RESOURCES (sebagian; Visual Reference Library tetap hanya di arsip) |
| `legacy-readmes/README_BACADULU_RESEARCH_MASTER.md` | Root | Duplikat `00_MASTER_CONTEXT_ALL_IN_ONE.md` (hanya beda baris akhir) |
| `legacy-readmes/README_BACADULU_SETUP.md` | Root | DEPLOYMENT |
| `legacy-readmes/README_BUILD.md` | Root | DEPLOYMENT |
| `legacy-readmes/README_FIX_FINAL.md` | Root | DEPLOYMENT, SECURITY |
| `legacy-readmes/README_CHAT_PHASE.md` | Root | PRD, ARCHITECTURE |
| `legacy-readmes/README_LANDING_REDESIGN_V1.txt` | Root | Riwayat (overlay landing; perintah robocopy ada di DEPLOYMENT bagian 8) |
| `legacy-readmes/INSTALL_CLAUDE_TEAM.md` | `INSTALL.md` | DEPLOYMENT bagian 11 |
| `legacy-readmes/CLAUDE.md.backup-boost-bootstrap.md` | `CLAUDE.md.backup` | Identik dengan `AGENTS.md` (hash sama) |

## Catatan

- Nilai warna, ukuran, dan keputusan "sidebar terkunci" pada arsip adalah keputusan historis; periksa dengan pemilik produk sebelum menghidupkannya kembali.
- Tautan Instagram referensi visual dirangkum di [FRONTEND_RESOURCES.md](../FRONTEND_RESOURCES.md).
