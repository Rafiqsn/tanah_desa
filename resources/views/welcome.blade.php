<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Input Data Warga — Model B.1</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f8fafc}
    h4{font-weight:600}
    .req:after{content:" *"; color:#d00}
  </style>
</head>
<body>
<div class="container py-4">
  <h4 class="mb-4">Form Data Induk Penduduk Desa (Model B.1)</h4>

  <form action="#" method="POST" autocomplete="off">
    @csrf

    @php
      $opsJK = ['L'=>'Laki-laki','P'=>'Perempuan'];
      $opsKawin = ['BELUM KAWIN','KAWIN','CERAI HIDUP','CERAI MATI'];
      $opsAgama = ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Kepercayaan'];
      $opsDidik = [
        'Tidak/Belum Sekolah','PAUD/TK','SD/Sederajat','SMP/Sederajat','SMA/Sederajat',
        'D1','D2','D3','D4/S1','S2','S3'
      ];
      $opsKewarganegaraan = ['WNI','WNA'];
      $opsKedudukan = ['Kepala Keluarga','Istri/Suami','Anak','Orang Tua','Fam Lain','Lainnya'];
    @endphp

    <div class="card mb-3">
      <div class="card-header">Identitas Dasar</div>
      <div class="card-body row g-3">
        <div class="col-md-5">
          <label class="form-label req">Nama Lengkap</label>
          <input type="text" name="nama_lengkap" class="form-control" required>
        </div>

        <div class="col-md-3">
          <label class="form-label req">Jenis Kelamin</label>
          <select name="jenis_kelamin" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach($opsJK as $v=>$t)<option value="{{ $v }}">{{ $t }}</option>@endforeach
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label req">Status Perkawinan</label>
          <select name="status_perkawinan" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach($opsKawin as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label req">Tempat Lahir</label>
          <input type="text" name="tempat_lahir" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label req">Tanggal Lahir</label>
          <input type="date" name="tanggal_lahir" class="form-control" required>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Agama • Pendidikan • Pekerjaan</div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label req">Agama</label>
          <select name="agama" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach($opsAgama as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label req">Pendidikan Terakhir</label>
          <select name="pendidikan_terakhir" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach($opsDidik as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Pekerjaan</label>
          <input type="text" name="pekerjaan" class="form-control" placeholder="contoh: Petani / Wiraswasta / PNS">
        </div>
      </div>
    </div>

    {{-- kolom "DAPAT MEMBACA HURUF" sengaja DIKELUARKAN sesuai permintaan --}}

    <div class="card mb-3">
      <div class="card-header">Kependudukan & Alamat</div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label req">Kewarganegaraan</label>
          <select name="kewarganegaraan" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach($opsKewarganegaraan as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label req">Kedudukan dalam Keluarga</label>
          <select name="kedudukan_keluarga" class="form-select" required>
            <option value="">-- pilih --</option>
            @foreach($opsKedudukan as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Alamat Lengkap</label>
          <input type="text" name="alamat_lengkap" class="form-control" placeholder="Dusun/RT/RW, Desa, Kec., Kab/Kota">
        </div>

        <div class="col-md-6">
          <label class="form-label">Nomor KTP (NIK)</label>
          <input type="text" name="nomor_ktp" class="form-control" maxlength="16" pattern="\d{16}" placeholder="16 digit">
          <div class="form-text">Isi 16 digit NIK (opsional untuk penduduk belum punya KTP).</div>
        </div>

        {{-- kolom "NOMOR KSK" sengaja DIKELUARKAN sesuai permintaan --}}
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Keterangan</div>
      <div class="card-body">
        <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan lain (opsional)"></textarea>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>
</body>
</html>
