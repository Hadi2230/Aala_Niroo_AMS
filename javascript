<script>
function showAssetFields() {
    var assetType = document.getElementById('asset_type').value;
    
    // مخفی کردن همه فیلدها
    document.getElementById('generator_fields').style.display = 'none';
    document.getElementById('motor_fields').style.display = 'none';
    document.getElementById('consumable_fields').style.display = 'none';
    
    // نمایش فیلدهای مربوطه
    if (assetType === 'ژنراتور') {
        document.getElementById('generator_fields').style.display = 'block';
    } else if (assetType === 'موتور_برق') {
        document.getElementById('motor_fields').style.display = 'block';
    } else if (assetType === 'اقلام_مصرفی') {
        document.getElementById('consumable_fields').style.display = 'block';
    }
}
</script>