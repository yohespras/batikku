# Batik AI Premium - PHP Native

Fitur:
- PHP native tanpa framework
- UI super premium responsive
- Mode gelap/terang tersimpan di browser
- Upload gambar JPG/PNG/WEBP
- Prediksi nama batik otomatis
- 2 model aktif: final dan best
- Mode ensemble final + best
- Riwayat prediksi terakhir

## Cara Menjalankan di XAMPP Windows
1. Extract folder `batik_native_premium` ke `htdocs`.
2. Buka CMD di folder project.
3. Install Python package:
   ```bash
   pip install -r requirements.txt
   ```
4. Pastikan Python bisa dipanggil dari PHP. Jika `python` tidak jalan, buka `config.php`, ubah:
   ```php
   const PYTHON_BIN = 'py';
   ```
5. Jalankan Apache XAMPP.
6. Buka:
   ```text
   http://localhost/batik_native_premium
   ```

## Catatan
- Model memakai input gambar 224x224.
- Jika prediksi gagal, cek TensorFlow sudah terinstall dan `shell_exec` tidak diblokir di `php.ini`.
- Jangan hapus folder `models`, `python`, `uploads`, dan `logs`.
