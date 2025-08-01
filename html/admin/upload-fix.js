// เพิ่มการตรวจสอบ CSRF Token ก่อนส่ง
const csrfToken = document.querySelector('form[enctype="multipart/form-data"] input[name="csrf_token"]');
if (!csrfToken || !csrfToken.value) {
    alert('ไม่พบ CSRF Token กรุณารีเฟรชหน้าและลองใหม่');
    return;
}
console.log('CSRF Token:', csrfToken.value); // เพื่อ debug
formData.append('csrf_token', csrfToken.value);