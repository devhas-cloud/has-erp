{{--
    Kompres foto di browser sebelum diunggah: sisi terpanjang dibatasi lalu
    disimpan ulang sebagai JPEG. Pakai: compressImage(file).then(function(f) {...}).
    Bila gagal dibaca atau hasilnya justru lebih besar, file asli dikembalikan.
--}}
@once
@push('page-scripts')
<script>
window.compressImage = function(file, options) {
    var opt = Object.assign({ maxSide: 1600, quality: 0.82 }, options || {});
    return new Promise(function(resolve) {
        if (!file || !/^image\/(jpeg|png|webp)$/i.test(file.type)) { resolve(file); return; }
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function() {
            var scale = Math.min(1, opt.maxSide / Math.max(img.naturalWidth, img.naturalHeight));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(img.naturalWidth * scale);
            canvas.height = Math.round(img.naturalHeight * scale);
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob(function(blob) {
                if (!blob || blob.size >= file.size) { resolve(file); return; }
                var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
            }, 'image/jpeg', opt.quality);
        };
        img.onerror = function() { URL.revokeObjectURL(url); resolve(file); };
        img.src = url;
    });
};
</script>
@endpush
@endonce
