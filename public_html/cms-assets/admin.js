(() => {
  "use strict";
  for (const form of document.querySelectorAll("form[data-confirm]")) {
    form.addEventListener("submit", event => {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  }

  const form = document.getElementById("post-form");
  if (!form) return;
  const field = document.getElementById("post-body");
  const save = document.getElementById("post-save");
  const status = document.getElementById("post-status");
  const upload = document.getElementById("cover-file");
  const cover = document.getElementById("cover-url");
  const coverImage = document.getElementById("cover-preview");
  const coverStatus = document.getElementById("cover-status");

  async function uploadImage(file) {
    const data = new FormData();
    data.set("action", "upload");
    data.set("csrf", form.elements.csrf.value);
    data.set("file", file);
    const response = await fetch("/admin/tin-tuc/", { method: "POST", body: data });
    const result = await response.json();
    if (!response.ok || !result.location) throw new Error(result.error || "Không tải được ảnh.");
    return result.location;
  }

  function updateCover() {
    const raw = cover.value.trim();
    try {
      const url = new URL(raw, location.origin);
      if (!raw || !["http:", "https:"].includes(url.protocol)) throw new Error();
      coverImage.src = url.href;
      coverImage.hidden = false;
    } catch {
      coverImage.removeAttribute("src");
      coverImage.hidden = true;
    }
  }

  cover.addEventListener("change", updateCover);
  upload.addEventListener("change", async () => {
    const file = upload.files[0];
    if (!file) return;
    coverStatus.textContent = "Đang tải ảnh…";
    save.disabled = true;
    upload.disabled = true;
    try {
      cover.value = await uploadImage(file);
      updateCover();
      coverStatus.textContent = "Đã tải ảnh. Lưu bài để cập nhật ảnh đại diện.";
    } catch (error) {
      coverStatus.textContent = error.message;
    } finally {
      save.disabled = false;
      upload.disabled = false;
      upload.value = "";
    }
  });

  function setSaveLabel() {
    save.textContent = { draft: "Lưu bản nháp", published: "Xuất bản / Cập nhật", scheduled: "Lên lịch đăng" }[status.value];
  }
  status.addEventListener("change", setSaveLabel);
  setSaveLabel();

  if (!window.tinymce) return;
  window.tinymce.init({
    selector: "#post-body",
    license_key: "gpl",
    menubar: "edit view insert format tools table",
    plugins: "autolink autosave charmap code fullscreen image link lists preview searchreplace table visualblocks wordcount",
    toolbar: "undo redo restoredraft | blocks | bold italic underline strikethrough | bullist numlist blockquote | link image table | preview fullscreen code",
    block_formats: "Đoạn văn=p;Tiêu đề 2=h2;Tiêu đề 3=h3;Tiêu đề 4=h4;Trích dẫn=blockquote;Mã=pre",
    height: 570,
    promotion: false,
    branding: false,
    convert_urls: false,
    paste_data_images: true,
    automatic_uploads: true,
    autosave_interval: "15s",
    autosave_retention: "2d",
    autosave_prefix: "vs-editor-" + (form.elements.id.value || "new") + "-",
    images_upload_handler: blob => uploadImage(blob.blob()).catch(error => Promise.reject({ message: error.message })),
    file_picker_types: "image",
    file_picker_callback: callback => {
      const picker = document.createElement("input");
      picker.type = "file";
      picker.accept = "image/jpeg,image/png,image/webp,image/gif,image/avif";
      picker.addEventListener("change", async () => {
        if (!picker.files[0]) return;
        try {
          callback(await uploadImage(picker.files[0]));
        } catch (error) {
          tinymce.get(field.id).notificationManager.open({ text: error.message, type: "error" });
        }
      });
      picker.click();
    }
  });
  form.addEventListener("submit", async event => {
    const editor = window.tinymce.get(field.id);
    if (!editor) return;
    event.preventDefault();
    save.disabled = true;
    try {
      const uploads = await editor.uploadImages();
      if (uploads.some(entry => !entry.status)) throw new Error("Có ảnh chưa tải được. Kiểm tra ảnh trước khi lưu.");
      editor.save();
      HTMLFormElement.prototype.submit.call(form);
    } catch (error) {
      editor.notificationManager.open({ text: error.message, type: "error" });
      save.disabled = false;
    }
  });
})();
