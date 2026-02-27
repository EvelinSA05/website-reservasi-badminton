function validasiNama() {
    console.log("validasiNama terpanggil");

    const inputPengguna = document.getElementById("id_pengguna").value.trim();
    const pola = /^[a-zA-Z\s]+$/; // hanya huruf dan spasi

    if (inputPengguna.length === 0) {
        alert("ID Pengguna tidak boleh kosong!");
        return false;
    }

    if (!pola.test(inputPengguna)) {
        alert("ID Pengguna hanya boleh berisi huruf dan spasi.");
        return false;
    }

    alert("ID Pengguna valid!");
    return true; 
}