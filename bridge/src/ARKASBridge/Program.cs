using System;
using System.Collections.Generic;
using System.Data.Common;
using System.Globalization;
using System.IO;
using Microsoft.Data.Sqlite;

namespace ARKASBridge;

internal static class Program
{
    private static int Main(string[] args)
    {
        try
        {
            SQLitePCL.Batteries_V2.Init();

            Dictionary<string, string> parsed = ParseArgs(args);
            if (!parsed.TryGetValue("db", out var dbPath) || string.IsNullOrWhiteSpace(dbPath))
            {
                return Fail("Parameter --db wajib diisi.");
            }
            if (!parsed.TryGetValue("command", out var command) || string.IsNullOrWhiteSpace(command))
            {
                return Fail("Parameter --command wajib diisi.");
            }

            parsed.TryGetValue("year", out var year);
            parsed.TryGetValue("table", out var table);
            parsed.TryGetValue("fund-source", out var fundSource);

            dbPath = Path.GetFullPath(dbPath);
            if (!File.Exists(dbPath))
            {
                return Fail("Database ARKAS tidak ditemukan.");
            }

            string password = Environment.GetEnvironmentVariable("ARKAS_BRIDGE_PASSWORD") ?? "";
            if (string.IsNullOrEmpty(password))
            {
                password = Console.In.ReadLine() ?? "";
            }
            if (string.IsNullOrEmpty(password))
            {
                return Fail("Password database tidak tersedia.");
            }

            string tempDb = Path.Combine(Path.GetTempPath(), $"SPJBOSP_ARKAS_{Guid.NewGuid():N}.db");
            try
            {
                File.Copy(dbPath, tempDb, overwrite: true);
                using SqliteConnection connection = OpenReadOnly(tempDb, password);

                switch (command.Trim().ToLowerInvariant())
                {
                    case "ping":
                        WritePing(connection);
                        break;
                    case "identity":
                        WriteIdentity(connection);
                        break;
                    case "years":
                        WriteYears(connection);
                        break;
                    case "fund-sources":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command fund-sources membutuhkan --year.");
                        }
                        WriteFundSources(connection, year.Trim());
                        break;
                    case "schema":
                        if (string.IsNullOrWhiteSpace(table))
                        {
                            return Fail("Command schema membutuhkan --table.");
                        }
                        WriteSchema(connection, table.Trim());
                        break;
                    case "profile":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command profile membutuhkan --year.");
                        }
                        WriteProfile(connection, year.Trim());
                        break;
                    case "pegawai":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command pegawai membutuhkan --year.");
                        }
                        WritePegawai(connection, year.Trim());
                        break;
                    case "ptk":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command ptk membutuhkan --year.");
                        }
                        WritePtk(connection, year.Trim());
                        break;
                    case "rekening":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command rekening membutuhkan --year.");
                        }
                        WriteRekening(connection, year.Trim());
                        break;
                    case "periods":
                        WritePeriods(connection);
                        break;
                    case "rkas":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command rkas membutuhkan --year.");
                        }
                        WriteRkas(connection, year.Trim(), fundSource);
                        break;
                    case "bku":
                        if (string.IsNullOrWhiteSpace(year))
                        {
                            return Fail("Command bku membutuhkan --year.");
                        }
                        WriteBku(connection, year.Trim(), fundSource);
                        break;
                    default:
                        return Fail("Command tidak dikenal: " + command);
                }

                return 0;
            }
            finally
            {
                try
                {
                    if (File.Exists(tempDb))
                    {
                        File.Delete(tempDb);
                    }
                }
                catch
                {
                    // Ignore deletion cleanup errors on temp copy
                }
            }
        }
        catch (Exception ex)
        {
            return Fail(ex.Message);
        }
    }

    private static SqliteConnection OpenReadOnly(string dbPath, string password)
    {
        string absoluteUri = new Uri(dbPath).AbsoluteUri;
        var builder = new SqliteConnectionStringBuilder
        {
            DataSource = absoluteUri + "?cipher=sqlcipher&legacy=4&mode=ro",
            Mode = SqliteOpenMode.ReadOnly,
            Password = password,
            Pooling = false
        };

        var con = new SqliteConnection(builder.ToString());
        con.Open();

        using (var cmd = con.CreateCommand())
        {
            cmd.CommandText = "PRAGMA query_only=ON;";
            cmd.ExecuteNonQuery();
        }

        using (var cmd = con.CreateCommand())
        {
            cmd.CommandText = "SELECT count(*) FROM sqlite_master;";
            cmd.ExecuteScalar();
        }

        return con;
    }

    private static void WritePing(SqliteConnection con)
    {
        using var cmd = con.CreateCommand();
        cmd.CommandText = "SELECT count(*) FROM sqlite_master;";
        Console.WriteLine("OK|" + Convert.ToInt64(cmd.ExecuteScalar()));
    }

    private static void WriteIdentity(SqliteConnection con)
    {
        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT COALESCE(npsn,''), COALESCE(nama,''), COALESCE(alamat,'')
FROM mst_sekolah
WHERE COALESCE(soft_delete,0)=0
LIMIT 1;";
        using var reader = cmd.ExecuteReader();
        if (!reader.Read())
        {
            throw new InvalidOperationException("Identitas sekolah tidak ditemukan.");
        }
        Console.WriteLine("NPSN|" + Clean(reader.GetString(0)));
        Console.WriteLine("NAMA_SEKOLAH|" + Clean(reader.GetString(1)));
        Console.WriteLine("ALAMAT|" + Clean(reader.GetString(2)));
    }

    private static void WriteYears(SqliteConnection con)
    {
        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT DISTINCT tahun_anggaran
FROM anggaran
WHERE COALESCE(soft_delete,0)=0
  AND tahun_anggaran IS NOT NULL
  AND CAST(tahun_anggaran AS INTEGER)>0
ORDER BY CAST(tahun_anggaran AS INTEGER);";
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            string value = Convert.ToString(reader.GetValue(0))?.Trim() ?? "";
            if (!string.IsNullOrWhiteSpace(value))
            {
                Console.WriteLine(value);
            }
        }
    }

    private static void WriteFundSources(SqliteConnection con, string year)
    {
        Console.WriteLine("SCHEMA|SUMBER_DANA|3");
        Console.WriteLine("FIELDS|ID_SUMBER_DANA|KODE|NAMA_SUMBER_DANA");

        string? anggaranSourceColumn = FindColumn(ReadTableColumns(con, "anggaran"), "id_ref_sumber_dana");
        if (anggaranSourceColumn == null)
        {
            return;
        }

        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT DISTINCT
            s.id_ref_sumber_dana,
            s.kode,
            s.nama_sumber_dana
FROM ref_sumber_dana s
INNER JOIN anggaran a
    ON a." + QuoteIdentifier(anggaranSourceColumn) + @" = s.id_ref_sumber_dana
WHERE COALESCE(a.soft_delete, 0) = 0
  AND CAST(a.tahun_anggaran AS TEXT) = $year
  AND COALESCE(s.is_hidden, 0) = 0
ORDER BY s.kode, s.nama_sumber_dana;";
        cmd.Parameters.AddWithValue("$year", year);
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            Console.WriteLine("DATA|" + Clean(Convert.ToString(reader.GetValue(0)) ?? "") + "|" +
                Clean(Convert.ToString(reader.GetValue(1)) ?? "") + "|" +
                Clean(Convert.ToString(reader.GetValue(2)) ?? ""));
            }
    }

    private static void WriteProfile(SqliteConnection con, string year)
    {
        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT DISTINCT
    COALESCE(sp.ks,''),
    COALESCE(sp.nip_ks,''),
    COALESCE(sp.bendahara,''),
    COALESCE(sp.nip_bendahara,''),
    COALESCE(sp.email_ks,''),
    COALESCE(sp.telp_ks,''),
    COALESCE(sp.email_bendahara,''),
    COALESCE(sp.telp_bendahara,'')
FROM anggaran a
INNER JOIN sekolah_penjab sp
    ON sp.id_penjab = a.id_penjab
   AND sp.tahun = CAST(a.tahun_anggaran AS INTEGER)
WHERE COALESCE(a.soft_delete,0)=0
  AND COALESCE(sp.soft_delete,0)=0
  AND CAST(a.tahun_anggaran AS TEXT)=$year
ORDER BY
    COALESCE(a.is_aktif,0) DESC,
    a.last_update DESC,
    sp.last_update DESC
LIMIT 1;";
        cmd.Parameters.AddWithValue("$year", year);
        using var reader = cmd.ExecuteReader();
        if (!reader.Read())
        {
            throw new InvalidOperationException("Data penanggung jawab sekolah untuk tahun " + year + " tidak ditemukan.");
        }
        Console.WriteLine("KEPALA_SEKOLAH|" + Clean(reader.GetString(0)));
        Console.WriteLine("NIP_KEPALA_SEKOLAH|" + Clean(reader.GetString(1)));
        Console.WriteLine("BENDAHARA|" + Clean(reader.GetString(2)));
        Console.WriteLine("NIP_BENDAHARA|" + Clean(reader.GetString(3)));
        Console.WriteLine("EMAIL_KEPALA_SEKOLAH|" + Clean(reader.GetString(4)));
        Console.WriteLine("TELP_KEPALA_SEKOLAH|" + Clean(reader.GetString(5)));
        Console.WriteLine("EMAIL_BENDAHARA|" + Clean(reader.GetString(6)));
        Console.WriteLine("TELP_BENDAHARA|" + Clean(reader.GetString(7)));
    }

    private static void WritePegawai(SqliteConnection con, string year)
    {
        Console.WriteLine("SCHEMA|PEGAWAI|1");
        Console.WriteLine("FIELDS|NAMA|NIP|NIK|NUPTK|JENIS_KELAMIN|STATUS_PEGAWAI|JENIS_PTK|JABATAN|NPWP|NAMA_BANK|NO_REKENING|STATUS_AKTIF");
        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT DISTINCT
    COALESCE(sp.ks,''),
    COALESCE(sp.nip_ks,''),
    COALESCE(sp.bendahara,''),
    COALESCE(sp.nip_bendahara,'')
FROM anggaran a
INNER JOIN sekolah_penjab sp
    ON sp.id_penjab = a.id_penjab
   AND sp.tahun = CAST(a.tahun_anggaran AS INTEGER)
WHERE COALESCE(a.soft_delete,0)=0
  AND COALESCE(sp.soft_delete,0)=0
  AND CAST(a.tahun_anggaran AS TEXT)=$year
ORDER BY
    COALESCE(a.is_aktif,0) DESC,
    a.last_update DESC,
    sp.last_update DESC
LIMIT 1;";
        cmd.Parameters.AddWithValue("$year", year);
        using var reader = cmd.ExecuteReader();
        if (!reader.Read())
        {
            throw new InvalidOperationException("Data pegawai penanggung jawab untuk tahun " + year + " tidak ditemukan.");
        }
        WritePegawaiRow(reader.GetString(0), reader.GetString(1), "KEPALA SEKOLAH");
        WritePegawaiRow(reader.GetString(2), reader.GetString(3), "BENDAHARA BOS");
    }

    private static void WritePegawaiRow(string nama, string nip, string jabatan)
    {
        if (!string.IsNullOrWhiteSpace(nama))
        {
            Console.WriteLine(string.Join("|", new[]
            {
                "DATA",
                Clean(nama),
                Clean(nip),
                "",
                "",
                "",
                "AKTIF",
                "",
                Clean(jabatan),
                "",
                "",
                "",
                "1"
            }));
        }
    }

    private static void WritePtk(SqliteConnection con, string year)
    {
        Console.WriteLine("SCHEMA|PTK|1");
        Console.WriteLine("FIELDS|PTK_ID_ARKAS|NAMA|NUPTK|JENIS_KELAMIN|JENIS_PTK|STATUS_AKTIF");
        Dictionary<string, string> columns = ReadTableColumns(con, "ptk");
        string? idCol = FindColumn(columns, "ptk_id", "id_ptk", "id");
        string? nameCol = FindColumn(columns, "nama", "nama_ptk", "nama_pegawai");
        string? nuptkCol = FindColumn(columns, "nuptk");
        string? jkCol = FindColumn(columns, "jenis_kelamin", "jk");
        string? jenisCol = FindColumn(columns, "jenis_ptk_arkas", "jenis_ptk", "status_kepegawaian");
        string? statusCol = FindColumn(columns, "status_aktif", "is_aktif", "aktif");
        string? softDelCol = FindColumn(columns, "soft_delete", "is_deleted");

        if (idCol == null) throw new InvalidOperationException("Kolom identitas PTK tidak ditemukan pada tabel ptk.");
        if (nameCol == null) throw new InvalidOperationException("Kolom nama PTK tidak ditemukan pada tabel ptk.");

        string[] selectCols = new[]
        {
            TextExpression("p", idCol),
            TextExpression("p", nameCol),
            TextExpression("p", nuptkCol),
            TextExpression("p", jkCol),
            TextExpression("p", jenisCol),
            (statusCol == null) ? "1" : NumberExpression("p", statusCol)
        };

        using var cmd = con.CreateCommand();
        cmd.CommandText = "SELECT " + string.Join(",", selectCols) + " FROM ptk p " +
            ((softDelCol == null) ? "" : ("WHERE COALESCE(p." + QuoteIdentifier(softDelCol) + ",0)=0 ")) +
            "ORDER BY p." + QuoteIdentifier(nameCol) + ";";

        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            string idVal = Clean(Convert.ToString(reader.GetValue(0)) ?? "");
            string nameVal = Clean(Convert.ToString(reader.GetValue(1)) ?? "");
            if (!string.IsNullOrWhiteSpace(idVal) && !string.IsNullOrWhiteSpace(nameVal))
            {
                Console.WriteLine(string.Join("|", new[]
                {
                    "DATA",
                    idVal,
                    nameVal,
                    Clean(Convert.ToString(reader.GetValue(2)) ?? ""),
                    Clean(Convert.ToString(reader.GetValue(3)) ?? ""),
                    Clean(Convert.ToString(reader.GetValue(4)) ?? ""),
                    ToFlexibleFlag(reader.GetValue(5))
                }));
            }
        }
    }

    private static void WriteRekening(SqliteConnection con, string year)
    {
        Console.WriteLine("SCHEMA|REKENING|1");
        Console.WriteLine("FIELDS|KODE_REKENING|NAMA_REKENING|IS_HONOR|IS_PPN|IS_PPH21|IS_PPH22|IS_PPH23|IS_PPH4|IS_SSPD|IS_BUKU|KATEGORI_SPJ");
        Dictionary<string, string> columns = ReadTableColumns(con, "ref_rekening");
        string? codeCol = FindColumn(columns, "kode_rekening", "kode");
        string? nameCol = FindColumn(columns, "nama_rekening", "rekening", "uraian_rekening", "uraian", "nama");
        if (codeCol == null) throw new InvalidOperationException("Kolom kode rekening tidak ditemukan pada ref_rekening.");

        string? isHonorCol = FindColumn(columns, "is_honor");
        string? isPpnCol = FindColumn(columns, "is_ppn");
        string? isPph21Col = FindColumn(columns, "is_pph21", "is_pph_21");
        string? isPph22Col = FindColumn(columns, "is_pph22", "is_pph_22");
        string? isPph23Col = FindColumn(columns, "is_pph23", "is_pph_23");
        string? isPph4Col = FindColumn(columns, "is_pph4", "is_pph_4");
        string? isSspdCol = FindColumn(columns, "is_sspd");
        string? isBukuCol = FindColumn(columns, "is_buku");
        string? softDelCol = FindColumn(columns, "soft_delete");

        string[] selectCols = new[]
        {
            TextExpression("rr", codeCol),
            TextExpression("rr", nameCol),
            NumberExpression("rr", isHonorCol),
            NumberExpression("rr", isPpnCol),
            NumberExpression("rr", isPph21Col),
            NumberExpression("rr", isPph22Col),
            NumberExpression("rr", isPph23Col),
            NumberExpression("rr", isPph4Col),
            NumberExpression("rr", isSspdCol),
            NumberExpression("rr", isBukuCol)
        };

        string? anggaranSourceColumn = FindColumn(ReadTableColumns(con, "anggaran"), "id_ref_sumber_dana");
        string sourceSelect = anggaranSourceColumn == null ? "" : ", " + QuoteIdentifier(anggaranSourceColumn);
        string sourcePartition = anggaranSourceColumn == null ? "tahun_anggaran" : QuoteIdentifier(anggaranSourceColumn) + ", tahun_anggaran";

        using var cmd = con.CreateCommand();
        cmd.CommandText = @"WITH latest_anggaran AS (
            SELECT id_anggaran" + sourceSelect + @",
                   ROW_NUMBER() OVER (
                       PARTITION BY " + sourcePartition + @"
                       ORDER BY COALESCE(is_aktif, 0) DESC, COALESCE(is_approve, 0) DESC, last_update DESC, create_date DESC
                   ) as rn
            FROM anggaran
            WHERE COALESCE(soft_delete, 0) = 0
              AND CAST(tahun_anggaran AS TEXT) = $year
        )
        SELECT DISTINCT " + string.Join(",", selectCols) + @"
        FROM ref_rekening rr
        INNER JOIN (
            SELECT DISTINCT r.kode_rekening
            FROM rapbs r
            INNER JOIN latest_anggaran a ON a.id_anggaran = r.id_anggaran AND a.rn = 1
            WHERE COALESCE(r.soft_delete,0) = 0
        ) used ON used.kode_rekening = rr." + QuoteIdentifier(codeCol) + " " +
        ((softDelCol == null) ? "" : ("WHERE COALESCE(rr." + QuoteIdentifier(softDelCol) + ",0)=0 ")) +
        "ORDER BY rr." + QuoteIdentifier(codeCol) + ";";

        cmd.Parameters.AddWithValue("$year", year);
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            string kode = Clean(Convert.ToString(reader.GetValue(0)) ?? "");
            string nama = Clean(Convert.ToString(reader.GetValue(1)) ?? "");
            string isHonor = ToFlag(reader.GetValue(2));
            string isPpn = ToFlag(reader.GetValue(3));
            string isPph21 = ToFlag(reader.GetValue(4));
            string isPph22 = ToFlag(reader.GetValue(5));
            string isPph23 = ToFlag(reader.GetValue(6));
            string isPph4 = ToFlag(reader.GetValue(7));
            string isSspd = ToFlag(reader.GetValue(8));
            string isBuku = ToFlag(reader.GetValue(9));
            string kategori = ResolveKategoriRekening(kode, isHonor);

            Console.WriteLine(string.Join("|", new[]
            {
                "DATA", kode, nama, isHonor, isPpn, isPph21, isPph22, isPph23, isPph4, isSspd, isBuku, kategori
            }));
        }
    }

    private static void WriteSchema(SqliteConnection con, string table)
    {
        foreach (char c in table)
        {
            if (!char.IsLetterOrDigit(c) && c != '_')
            {
                throw new InvalidOperationException("Nama tabel tidak valid.");
            }
        }
        using (var checkCmd = con.CreateCommand())
        {
            checkCmd.CommandText = "SELECT count(*) FROM sqlite_master WHERE type='table' AND name=$name;";
            checkCmd.Parameters.AddWithValue("$name", table);
            if (Convert.ToInt32(checkCmd.ExecuteScalar()) == 0)
            {
                throw new InvalidOperationException("Tabel '" + table + "' tidak ditemukan.");
            }
        }

        Console.WriteLine("SCHEMA|" + table);
        using var cmd = con.CreateCommand();
        cmd.CommandText = "PRAGMA table_info([" + table + "]);";
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            Console.WriteLine("COLUMN|" +
                Clean(Convert.ToString(reader["cid"]) ?? "") + "|" +
                Clean(Convert.ToString(reader["name"]) ?? "") + "|" +
                Clean(Convert.ToString(reader["type"]) ?? "") + "|" +
                Clean(Convert.ToString(reader["notnull"]) ?? "") + "|" +
                Clean(Convert.ToString(reader["dflt_value"]) ?? "") + "|" +
                Clean(Convert.ToString(reader["pk"]) ?? ""));
        }
    }

    private static void WriteRkas(SqliteConnection con, string year, string? fundSource)
    {
        Console.WriteLine("SCHEMA|RKAS|2");
        Console.WriteLine("FIELDS|ID_REF_SUMBER_DANA|ID_RAPBS|ID_ANGGARAN|ID_REF_KODE|KODE_KEGIATAN|NAMA_KEGIATAN|KODE_REKENING|ID_BARANG|URAIAN|SATUAN|HARGA_SATUAN|VOL_TW1|VOL_TW2|VOL_TW3|VOL_TW4|TW_1|TW_2|TW_3|TW_4|VOLUME_TOTAL|JUMLAH");

        string? anggaranSourceColumn = FindColumn(ReadTableColumns(con, "anggaran"), "id_ref_sumber_dana");
        string sourceExpression = anggaranSourceColumn == null
            ? "$fund_source"
            : "a." + QuoteIdentifier(anggaranSourceColumn);
        string sourcePartition = anggaranSourceColumn == null
            ? "tahun_anggaran"
            : QuoteIdentifier(anggaranSourceColumn) + ", tahun_anggaran";

        using var cmd = con.CreateCommand();
        cmd.CommandText = @"WITH latest_anggaran AS
(
    SELECT id_anggaran" + (anggaranSourceColumn == null ? "" : ", " + QuoteIdentifier(anggaranSourceColumn)) + @",
           ROW_NUMBER() OVER (
               PARTITION BY " + sourcePartition + @"
               ORDER BY COALESCE(is_aktif, 0) DESC, COALESCE(is_approve, 0) DESC, last_update DESC, create_date DESC
           ) as rn
    FROM anggaran
    WHERE COALESCE(soft_delete, 0) = 0
      AND CAST(tahun_anggaran AS TEXT) = $year
),
periode AS
(
    SELECT
        rp.id_rapbs,
        MAX(CASE WHEN rp.id_periode IN (1,2,3,4) THEN 1 ELSE 0 END) AS has_triwulan,
        SUM(CASE WHEN rp.id_periode = 1 THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw1_direct,
        SUM(CASE WHEN rp.id_periode = 2 THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw2_direct,
        SUM(CASE WHEN rp.id_periode = 3 THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw3_direct,
        SUM(CASE WHEN rp.id_periode = 4 THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw4_direct,
        SUM(CASE WHEN rp.id_periode = 1 THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw1_direct,
        SUM(CASE WHEN rp.id_periode = 2 THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw2_direct,
        SUM(CASE WHEN rp.id_periode = 3 THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw3_direct,
        SUM(CASE WHEN rp.id_periode = 4 THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw4_direct,
        SUM(CASE WHEN rp.id_periode IN (81,82,83) THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw1_month,
        SUM(CASE WHEN rp.id_periode IN (84,85,86) THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw2_month,
        SUM(CASE WHEN rp.id_periode IN (87,88,89) THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw3_month,
        SUM(CASE WHEN rp.id_periode IN (90,91,92) THEN COALESCE(rp.volume,0) ELSE 0 END) AS vol_tw4_month,
        SUM(CASE WHEN rp.id_periode IN (81,82,83) THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw1_month,
        SUM(CASE WHEN rp.id_periode IN (84,85,86) THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw2_month,
        SUM(CASE WHEN rp.id_periode IN (87,88,89) THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw3_month,
        SUM(CASE WHEN rp.id_periode IN (90,91,92) THEN COALESCE(rp.jumlah,0) ELSE 0 END) AS tw4_month
    FROM rapbs_periode rp
    WHERE COALESCE(rp.soft_delete,0) = 0
    GROUP BY rp.id_rapbs
)
SELECT
    COALESCE(" + sourceExpression + @",''),
    COALESCE(r.id_rapbs,''),
    COALESCE(r.id_anggaran,''),
    COALESCE(r.id_ref_kode,''),
    COALESCE(rk.id_kode,''),
    COALESCE(rk.uraian_kode,''),
    COALESCE(r.kode_rekening,''),
    COALESCE(r.id_barang,''),
    COALESCE(r.uraian,''),
    COALESCE(r.satuan,''),
    COALESCE(r.harga_satuan,0),
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.vol_tw1_direct,0) ELSE COALESCE(p.vol_tw1_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.vol_tw2_direct,0) ELSE COALESCE(p.vol_tw2_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.vol_tw3_direct,0) ELSE COALESCE(p.vol_tw3_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.vol_tw4_direct,0) ELSE COALESCE(p.vol_tw4_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.tw1_direct,0) ELSE COALESCE(p.tw1_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.tw2_direct,0) ELSE COALESCE(p.tw2_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.tw3_direct,0) ELSE COALESCE(p.tw3_month,0) END,
    CASE WHEN COALESCE(p.has_triwulan,0) = 1 THEN COALESCE(p.tw4_direct,0) ELSE COALESCE(p.tw4_month,0) END,
    COALESCE(r.volume,0),
    COALESCE(r.jumlah,0)
FROM rapbs r
INNER JOIN latest_anggaran a ON a.id_anggaran = r.id_anggaran AND a.rn = 1
LEFT JOIN (
    SELECT DISTINCT id_ref_kode, id_kode, uraian_kode
    FROM ref_kode
    WHERE CAST(tahun AS TEXT) = $year
) rk ON rk.id_ref_kode = r.id_ref_kode
LEFT JOIN periode p ON p.id_rapbs = r.id_rapbs
WHERE COALESCE(r.soft_delete,0) = 0
ORDER BY r.kode_rekening, r.uraian, r.id_rapbs;";

        cmd.Parameters.AddWithValue("$year", year);
        if (anggaranSourceColumn == null) {
            cmd.Parameters.AddWithValue("$fund_source", fundSource ?? "");
        }
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            string[] row = new string[21];
            for (int i = 0; i < 21; i++)
            {
                object val = reader.GetValue(i);
                string strVal = val switch
                {
                    double d => d.ToString("0.################", CultureInfo.InvariantCulture),
                    float f => f.ToString("0.################", CultureInfo.InvariantCulture),
                    decimal m => m.ToString(CultureInfo.InvariantCulture),
                    _ => Clean(Convert.ToString(val, CultureInfo.InvariantCulture) ?? "")
                };
                row[i] = strVal;
            }
            Console.WriteLine("DATA|" + string.Join("|", row));
        }
    }

    private static void WritePeriods(SqliteConnection con)
    {
        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT id_periode, periode FROM ref_periode ORDER BY id_periode;";
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            Console.WriteLine(Clean(Convert.ToString(reader["id_periode"]) ?? "") + "|" + Clean(Convert.ToString(reader["periode"]) ?? ""));
        }
    }

    private static void WriteBku(SqliteConnection con, string year, string? fundSource)
    {
        Console.WriteLine("SCHEMA|BKU|1");
        Console.WriteLine("FIELDS|ID_REF_SUMBER_DANA|ID_KAS_UMUM|ID_KAS_NOTA|ID_RAPBS_PERIODE|ID_RAPBS|ID_ANGGARAN|ID_REF_BKU|PARENT_ID_KAS_UMUM|TANGGAL_TRANSAKSI|NO_BUKTI|KODE_REKENING|URAIAN|URAIAN_PAJAK|VOLUME|JUMLAH|STATUS_BKU|KODE_BKU|REK_BKU|KATEGORI_BKU|IS_SPJ|IS_PPN|IS_PPH21|IS_PPH22|IS_PPH23|IS_PPH4|IS_SSPD|TANGGAL_NOTA|NO_NOTA|NAMA_TOKO|ALAMAT_TOKO|NO_TELP_TOKO|IS_BADAN_USAHA|NPWP_REKANAN|TOTAL_NOTA|HAS_PPN_NOTA|HAS_PPH22_NOTA|IS_SIPLAH");

        string? anggaranSourceColumn = FindColumn(ReadTableColumns(con, "anggaran"), "id_ref_sumber_dana");
        string sourceExpression = anggaranSourceColumn == null
            ? "$fund_source"
            : "a." + QuoteIdentifier(anggaranSourceColumn);

        using var cmd = con.CreateCommand();
        cmd.CommandText = @"SELECT
            COALESCE(" + sourceExpression + @",''),
            COALESCE(k.id_kas_umum,''),
    COALESCE(k.id_kas_nota,''),
    COALESCE(k.id_rapbs_periode,''),
    COALESCE(rp.id_rapbs,''),
    COALESCE(k.id_anggaran,''),
    COALESCE(k.id_ref_bku,0),
    COALESCE(k.parent_id_kas_umum,''),
    COALESCE(strftime('%Y-%m-%d', k.tanggal_transaksi), ''),
    COALESCE(k.no_bukti,''),
    COALESCE(k.kode_rekening,''),
    COALESCE(k.uraian,''),
    COALESCE(k.uraian_pajak,''),
    COALESCE(k.volume,0),
    COALESCE(k.saldo,0),
    COALESCE(k.status_bku,''),
    CASE k.id_ref_bku
        WHEN 1  THEN ''
        WHEN 2  THEN 'BBU'
        WHEN 3  THEN 'BBU'
        WHEN 4  THEN 'BPU'
        WHEN 5  THEN 'SAB'
        WHEN 6  THEN 'BBU'
        WHEN 7  THEN 'BPU'
        WHEN 8  THEN 'SAB'
        WHEN 9  THEN 'SAT'
        WHEN 10 THEN 'PBT'
        WHEN 11 THEN 'PBS'
        WHEN 12 THEN 'PBS'
        WHEN 13 THEN 'PSS'
        WHEN 14 THEN 'STS'
        WHEN 15 THEN 'BNU'
        WHEN 23 THEN 'BBU'
        WHEN 24 THEN 'BPU'
        WHEN 25 THEN 'BBU'
        WHEN 26 THEN 'BBU'
        WHEN 27 THEN 'BPU'
        WHEN 28 THEN 'SAB'
        WHEN 29 THEN 'SAT'
        WHEN 30 THEN 'PBT'
        WHEN 31 THEN 'PBS'
        WHEN 32 THEN 'PBS'
        WHEN 33 THEN 'PSS'
        WHEN 34 THEN 'PTS'
        WHEN 35 THEN 'BNU'
        ELSE ''
    END,
    CASE k.id_ref_bku
        WHEN 1  THEN 'Saldo Awal'
        WHEN 2  THEN 'Terima Dana BOS'
        WHEN 3  THEN 'Tarik Tunai'
        WHEN 4  THEN 'Kas Keluar'
        WHEN 5  THEN 'Setor Tunai'
        WHEN 6  THEN 'Bunga Bank'
        WHEN 7  THEN 'Pajak Bunga'
        WHEN 8  THEN 'Saldo Awal Bank'
        WHEN 9  THEN 'Saldo Awal Tunai'
        WHEN 10 THEN 'Pajak Belanja Terima'
        WHEN 11 THEN 'Pajak Belanja Setor'
        WHEN 12 THEN 'Pergeseran Tunai'
        WHEN 13 THEN 'Pergeseran Setor'
        WHEN 14 THEN 'Pengembalian Dana BOS'
        WHEN 15 THEN 'Kas Keluar Non Tunai'
        WHEN 23 THEN 'Tarik Tunai Sisa'
        WHEN 24 THEN 'Kas Keluar Sisa'
        WHEN 25 THEN 'Setor Tunai Sisa'
        WHEN 26 THEN 'Bunga Bank Sisa'
        WHEN 27 THEN 'Pajak Bunga Sisa'
        WHEN 28 THEN 'Saldo Awal Bank Sisa'
        WHEN 29 THEN 'Saldo Awal Tunai Sisa'
        WHEN 30 THEN 'Pajak Belanja Terima Sisa'
        WHEN 31 THEN 'Pajak Belanja Setor Sisa'
        WHEN 32 THEN 'Pergeseran Tunai'
        WHEN 33 THEN 'Pergeseran Setor'
        WHEN 34 THEN 'Pengembalian Dana BOS'
        WHEN 35 THEN 'Kas Keluar Sisa Non Tunai'
        ELSE 'Lainnya'
    END,
    CASE
        WHEN k.id_ref_bku IN (4,15,24,35) THEN 'BELANJA'
        WHEN k.id_ref_bku IN (10,11,30,31) THEN 'PAJAK'
        WHEN k.id_ref_bku IN (3,5,12,13,23,25,32,33) THEN 'PERGESERAN'
        WHEN k.id_ref_bku IN (1,8,9,28,29) THEN 'SALDO_AWAL'
        WHEN k.id_ref_bku = 2 THEN 'PENERIMAAN_BOS'
        WHEN k.id_ref_bku IN (6,26) THEN 'BUNGA_BANK'
        WHEN k.id_ref_bku IN (7,27) THEN 'PAJAK_BANK'
        WHEN k.id_ref_bku IN (14,34) THEN 'PENGEMBALIAN_BOS'
        ELSE 'LAINNYA'
    END,
    CASE WHEN k.id_ref_bku IN (4,15,24,35) THEN 1 ELSE 0 END,
    COALESCE(k.is_ppn,0),
    COALESCE(k.is_pph_21,0),
    COALESCE(k.is_pph_22,0),
    COALESCE(k.is_pph_23,0),
    COALESCE(k.is_pph_4,0),
    COALESCE(k.is_sspd,0),
    COALESCE(strftime('%Y-%m-%d', n.tanggal_nota), ''),
    COALESCE(n.no_nota,''),
    COALESCE(n.nama_toko,''),
    COALESCE(n.alamat_toko,''),
    COALESCE(n.no_telp,''),
    COALESCE(n.is_badan_usaha,0),
    COALESCE(n.npwp,''),
    COALESCE(n.total,0),
    COALESCE(n.has_ppn,0),
    COALESCE(n.has_pph_22,0),
    COALESCE(n.is_beli_di_siplah,0)
FROM kas_umum k
INNER JOIN anggaran a ON a.id_anggaran = k.id_anggaran
LEFT JOIN rapbs_periode rp ON rp.id_rapbs_periode = k.id_rapbs_periode AND COALESCE(rp.soft_delete,0) = 0
LEFT JOIN kas_umum_nota n ON n.id_kas_nota = k.id_kas_nota AND COALESCE(n.soft_delete,0) = 0
WHERE COALESCE(k.soft_delete,0) = 0
  AND COALESCE(a.soft_delete,0) = 0
  AND CAST(a.tahun_anggaran AS TEXT) = $year
ORDER BY k.tanggal_transaksi, k.create_date, k.id_kas_umum;";

        cmd.Parameters.AddWithValue("$year", year);
        if (anggaranSourceColumn == null) {
            cmd.Parameters.AddWithValue("$fund_source", fundSource ?? "");
        }
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            string[] row = new string[reader.FieldCount];
            for (int i = 0; i < reader.FieldCount; i++)
            {
                object val = reader.GetValue(i);
                string strVal = val switch
                {
                    double d => d.ToString("0.################", CultureInfo.InvariantCulture),
                    float f => f.ToString("0.################", CultureInfo.InvariantCulture),
                    decimal m => m.ToString(CultureInfo.InvariantCulture),
                    _ => Clean(Convert.ToString(val, CultureInfo.InvariantCulture) ?? "")
                };
                row[i] = strVal;
            }
            Console.WriteLine("DATA|" + string.Join("|", row));
        }
    }

    private static string Clean(string s)
    {
        return s.Replace("\r", " ").Replace("\n", " ").Replace("|", "/").Trim();
    }

    private static Dictionary<string, string> ReadTableColumns(SqliteConnection con, string tableName)
    {
        var dict = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        using var cmd = con.CreateCommand();
        cmd.CommandText = "PRAGMA table_info(" + QuoteIdentifier(tableName) + ");";
        using var reader = cmd.ExecuteReader();
        while (reader.Read())
        {
            string text = Convert.ToString(reader["name"])?.Trim() ?? "";
            if (!string.IsNullOrWhiteSpace(text))
            {
                dict[text] = text;
            }
        }
        if (dict.Count == 0)
        {
            throw new InvalidOperationException("Tabel '" + tableName + "' tidak ditemukan atau tidak memiliki kolom.");
        }
        return dict;
    }

    private static string? FindColumn(Dictionary<string, string> columns, params string[] candidates)
    {
        foreach (string key in candidates)
        {
            if (columns.TryGetValue(key, out string? value))
            {
                return value;
            }
        }
        return null;
    }

    private static string QuoteIdentifier(string identifier)
    {
        foreach (char c in identifier)
        {
            if (!char.IsLetterOrDigit(c) && c != '_')
            {
                throw new InvalidOperationException("Identifier database tidak valid: " + identifier);
            }
        }
        return "[" + identifier + "]";
    }

    private static string TextExpression(string alias, string? column)
    {
        return column != null ? $"COALESCE({alias}.{QuoteIdentifier(column)},'')" : "''";
    }

    private static string NumberExpression(string alias, string? column)
    {
        return column != null ? $"COALESCE({alias}.{QuoteIdentifier(column)},0)" : "0";
    }

    private static string ToFlag(object value)
    {
        if (value == null || value is DBNull) return "0";
        if (Convert.ToDouble(value, CultureInfo.InvariantCulture) != 0.0) return "1";
        return "0";
    }

    private static string ToFlexibleFlag(object value)
    {
        if (value == null || value is DBNull) return "0";
        string text = Convert.ToString(value, CultureInfo.InvariantCulture)?.Trim() ?? "";
        if (double.TryParse(text, NumberStyles.Any, CultureInfo.InvariantCulture, out var result))
        {
            return result != 0.0 ? "1" : "0";
        }
        if (!text.Equals("AKTIF", StringComparison.OrdinalIgnoreCase) &&
            !text.Equals("TRUE", StringComparison.OrdinalIgnoreCase) &&
            !text.Equals("YA", StringComparison.OrdinalIgnoreCase) &&
            !text.Equals("Y", StringComparison.OrdinalIgnoreCase))
        {
            return "0";
        }
        return "1";
    }

    private static string ResolveKategoriRekening(string kodeRekening, string isHonor)
    {
        string text = kodeRekening.Trim();
        if (isHonor == "1") return "HONOR";
        if (text.StartsWith("5.1.02.01", StringComparison.OrdinalIgnoreCase)) return "BARANG";
        if (text.StartsWith("5.1.02.02", StringComparison.OrdinalIgnoreCase)) return "JASA";
        if (text.StartsWith("5.1.02.04", StringComparison.OrdinalIgnoreCase)) return "PERJALANAN_DINAS";
        if (text.StartsWith("5.2", StringComparison.OrdinalIgnoreCase)) return "BELANJA_MODAL";
        return "LAINNYA";
    }

    private static Dictionary<string, string> ParseArgs(string[] args)
    {
        var dict = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
        for (int i = 0; i < args.Length; i++)
        {
            if (args[i].StartsWith("--"))
            {
                string key = args[i].Substring(2);
                if (i + 1 < args.Length && !args[i + 1].StartsWith("--"))
                {
                    dict[key] = args[++i];
                }
                else
                {
                    dict[key] = "true";
                }
            }
        }
        return dict;
    }

    private static int Fail(string msg)
    {
        Console.Error.WriteLine(msg);
        return 1;
    }
}
