document.addEventListener("DOMContentLoaded", () => {
    const editBtn = document.getElementById("edit-btn");
    const saveBtn = document.getElementById("save-btn");
    const cancelBtn = document.getElementById("cancel-btn");

    const usernameText = document.getElementById("username-text");
    const usernameInput = document.getElementById("username-input");

    const avatarField = document.getElementById("avatar-field"); // asegúrate de tener este div
    const avatarInput = document.getElementById("avatar-input");
    const avatarPreview = document.getElementById("avatar-preview");

    if (!editBtn || !saveBtn || !cancelBtn || !usernameText || !usernameInput || !avatarInput || !avatarPreview) {
        console.warn("Perfil: algún elemento no existe en el DOM.");
        return;
    }

    const showEditMode = () => {
        usernameText.style.display = "none";
        usernameInput.style.display = "block";
        if(avatarField) avatarField.style.display = "block";

        editBtn.style.display = "none";
        saveBtn.style.display = "inline-block";
        cancelBtn.style.display = "inline-block";
    };

    const hideEditMode = () => {
        usernameText.style.display = "block";
        usernameInput.style.display = "none";
        if(avatarField) avatarField.style.display = "none";

        editBtn.style.display = "inline-block";
        saveBtn.style.display = "none";
        cancelBtn.style.display = "none";

        // restaurar valores originales
        usernameInput.value = usernameText.textContent;
        if(avatarField && avatarInput.files.length === 0) {
            avatarPreview.src = avatarPreview.dataset.original || avatarPreview.src;
        }
    };

    editBtn.addEventListener("click", showEditMode);
    cancelBtn.addEventListener("click", hideEditMode);

    // Preview del avatar en tiempo real
    avatarInput.addEventListener("change", () => {
        const file = avatarInput.files[0];
        if(file){
            avatarPreview.src = URL.createObjectURL(file);
        }
    });

    // guardar estado original del avatar para poder revertir
    avatarPreview.dataset.original = avatarPreview.src;
});