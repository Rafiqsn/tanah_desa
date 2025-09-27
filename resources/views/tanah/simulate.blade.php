@extends('layouts.app')

@section('content')
<div class="container">
  <h4 class="mb-4">Input Buku Tanah Desa (A.6)</h4>

  <form  method="POST">
    @csrf

    {{-- Identitas --}}
    <div class="card mb-3">
      <div class="card-header">Identitas</div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">Nomor Urut</label>
          <input type="text" name="nomor_urut" class="form-control" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">Nama Pemilik / Badan Hukum</label>
          <input type="text" name="nama_pemilik" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Jumlah (m²)</label>
          <input type="number" name="jumlah_m2" class="form-control" step="0.01" required>
        </div>
      </div>
    </div>

    {{-- Status Hak Tanah --}}
    <div class="card mb-3">
      <div class="card-header">Status Hak Tanah (m²)</div>
      <div class="card-body row g-3">
        <div class="col-md-2"><label>HM</label><input type="number" name="hm" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>HGB</label><input type="number" name="hgb" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>HP</label><input type="number" name="hp" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>HGU</label><input type="number" name="hgu" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>HPL</label><input type="number" name="hpl" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>MA</label><input type="number" name="ma" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>VI</label><input type="number" name="vi" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>TN</label><input type="number" name="tn" class="form-control" step="0.01"></div>
      </div>
    </div>

    {{-- Penggunaan Tanah --}}
    <div class="card mb-3">
      <div class="card-header">Penggunaan Tanah (m²)</div>
      <div class="card-body row g-3">
        <div class="col-md-2"><label>Perumahan</label><input type="number" name="perumahan" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Perdagangan & Jasa</label><input type="number" name="perdagangan_jasa" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Perkantoran</label><input type="number" name="perkantoran" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Industri</label><input type="number" name="industri" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Fasilitas Umum</label><input type="number" name="fasilitas_umum" class="form-control" step="0.01"></div>

        <div class="col-md-2"><label>Sawah</label><input type="number" name="sawah" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Tegalan</label><input type="number" name="tegalan" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Perkebunan</label><input type="number" name="perkebunan" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Peternakan/Perikanan</label><input type="number" name="peternakan_perikanan" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Hutan Belukar</label><input type="number" name="hutan_belukar" class="form-control" step="0.01"></div>
        <div class="col-md-2"><label>Hutan Lebat/Lindung</label><input type="number" name="hutan_lindung" class="form-control" step="0.01"></div>
      </div>
    </div>

    {{-- Lain-lain --}}
    <div class="card mb-3">
      <div class="card-header">Lain-lain</div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label>Mutasi Tanah di Desa</label>
          <input type="text" name="mutasi" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Tanah Kosong (m²)</label>
          <input type="number" name="tanah_kosong" class="form-control" step="0.01">
        </div>
        <div class="col-md-3">
          <label>Lain-lain (m²)</label>
          <input type="number" name="lain_lain" class="form-control" step="0.01">
        </div>
        <div class="col-md-3">
          <label>Keterangan</label>
          <input type="text" name="keterangan" class="form-control">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
  </form>
</div>
@endsection
