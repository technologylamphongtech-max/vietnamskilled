(() => {
  "use strict";

  window.vsPostMail = async function (payload) {
    const base = location.pathname.startsWith("/brand/") ? "/brand" : "";
    const response = await fetch(base + "/lien-he/send-mail.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok || result.ok !== true) {
      throw new Error("VietnamSkilled mail delivery failed");
    }
    return result;
  };

  function initContact() {
    const form = document.querySelector("form[data-vs-contact]");
    if (!form) return;
    const wrapper = form.closest(".w-form") || form.parentElement;
    const done = wrapper.querySelector(".w-form-done");
    const fail = wrapper.querySelector(".w-form-fail");
    const button = form.querySelector('[type="submit"]');
    const original = button.value;
    if (done) done.setAttribute("role", "status");
    if (fail) fail.setAttribute("role", "alert");

    form.addEventListener("submit", async event => {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (button.disabled) return;
      const values = new FormData(form);
      const name = String(values.get("name") || "").trim();
      const email = String(values.get("email") || "").trim();
      const message = String(values.get("Nachricht") || "").trim();
      button.disabled = true;
      button.value = button.dataset.wait || "Đang gửi...";
      if (fail) fail.style.display = "none";
      try {
        await window.vsPostMail({
          form: "contact", name, email,
          fields: { "Email": email, "Nội dung": message },
          page: location.origin + location.pathname
        });
        form.style.display = "none";
        if (done) done.style.display = "block";
      } catch {
        if (fail) fail.style.display = "block";
        button.disabled = false;
        button.value = original;
      }
    }, true);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initContact);
  } else {
    initContact();
  }
})();

