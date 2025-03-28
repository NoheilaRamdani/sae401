// assets/app.js
console.log('AssetMapper test');

// HIDING NAV SEPARATOR WITH ACTIVE
document.addEventListener("DOMContentLoaded", function () {
    const activeItem = document.querySelector(".links li.active");

    if (activeItem) {
        // Hide separator APRÈS .active
        const nextSeparator = activeItem.nextElementSibling;
        if (nextSeparator && nextSeparator.classList.contains("separator")) {
            nextSeparator.style.opacity = "0";
            nextSeparator.style.visibility = "hidden";
        }

        // Hide le separator AVANT .active
        const prevSeparator = activeItem.previousElementSibling;
        if (prevSeparator && prevSeparator.classList.contains("separator")) {
            prevSeparator.style.opacity = "0";
            prevSeparator.style.visibility = "hidden";
        }
    }



    // FADE DES POPUPS
    const popups = document.querySelectorAll(".popup");

    popups.forEach((popup) => {
        if (popup) {
            // Ajout de la classe pour le fade-in
            popup.classList.add("show");

            setTimeout(() => {
                popup.classList.add("fade-out");

                // Supprime la div après l'animation
                setTimeout(() => {
                    popup.style.display = "none";
                }, 500); // Temps pour que le fade-out se termine
            }, 4000); // 4 secondes d'affichage avant de commencer le fade-out
        }
    });
});
