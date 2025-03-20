// assets/app.js
console.log('AssetMapper test');

document.addEventListener("DOMContentLoaded", function () {
    const activeItem = document.querySelector(".links li.active");

    if (activeItem) {
        // Cacher le separator APRÈS .active
        const nextSeparator = activeItem.nextElementSibling;
        if (nextSeparator && nextSeparator.classList.contains("separator")) {
            nextSeparator.style.opacity = "0";
            nextSeparator.style.visibility = "hidden";
        }

        // Cacher le separator AVANT .active
        const prevSeparator = activeItem.previousElementSibling;
        if (prevSeparator && prevSeparator.classList.contains("separator")) {
            prevSeparator.style.opacity = "0";
            prevSeparator.style.visibility = "hidden";
        }
    }
});
