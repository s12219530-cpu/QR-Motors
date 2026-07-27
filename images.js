const imageExtensions = [
    "jpg", "jpeg", "png", "webp", "jfif", "avif",
    "JPG", "JPEG", "PNG", "WEBP", "JFIF", "AVIF"
];

function loadFlexibleImage(img) {
    const imageName = img.getAttribute("data-img");
    let index = 0;

    function showFallback() {
        const label = (img.alt || imageName || "QR Motors").replace(/[<>&"']/g, "");
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 720">
            <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#07110f"/><stop offset="1" stop-color="#176653"/></linearGradient></defs>
            <rect width="1200" height="720" fill="url(#g)"/>
            <circle cx="1030" cy="110" r="230" fill="#b8f3c8" opacity=".08"/>
            <path d="M265 455h660l-55-105c-20-38-58-62-101-62H477c-40 0-77 20-98 54l-70 113z" fill="#b8f3c8" opacity=".8"/>
            <path d="M360 455l65-105c10-17 29-27 49-27h288c24 0 45 13 56 34l51 98" fill="none" stroke="#07110f" stroke-width="14"/>
            <circle cx="405" cy="475" r="63" fill="#07110f" stroke="#b8f3c8" stroke-width="13"/><circle cx="810" cy="475" r="63" fill="#07110f" stroke="#b8f3c8" stroke-width="13"/>
            <text x="60" y="100" fill="#b8f3c8" font-family="Arial,sans-serif" font-size="35" font-weight="700">QR MOTORS</text>
            <text x="600" y="630" text-anchor="middle" fill="white" font-family="Arial,sans-serif" font-size="36">${label}</text>
        </svg>`;
        img.onerror = null;
        img.src = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
        img.classList.add("fallback-img");
    }

    function tryNextExtension() {
        if (index >= imageExtensions.length) {
            showFallback();
            return;
        }

        img.src = "img/" + imageName + "." + imageExtensions[index];
        index++;
    }

    img.onerror = tryNextExtension;
    tryNextExtension();
}

document.addEventListener("DOMContentLoaded", function () {
    const flexibleImages = document.querySelectorAll("img[data-img]");

    flexibleImages.forEach(function (img) {
        loadFlexibleImage(img);
    });
});
