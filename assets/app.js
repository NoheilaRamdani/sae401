console.log('AssetMapper test');

/**
 * Gestion des tâches terminées (checkboxes, mise à jour de l'apparence, synchronisation).
 * Utilisé par les pages avec des listes de tâches ou un calendrier.
 * @param {{selector: string, hasCalendar: boolean}} config - Configuration pour adapter la logique.
 * @param {string} config.selector - Sélecteur des éléments de tâche (.task-container).
 * @param {boolean} config.hasCalendar - Indique si un calendrier FullCalendar est présent.
 * @param {Object} config.calendar - Instance du calendrier FullCalendar (si hasCalendar est true).
 */
function initTaskManager(config) {
    const { selector, hasCalendar = false, calendar = null } = config;

    // Fonction pour mettre à jour l'apparence d'une tâche
    function updateTaskAppearance(eventId, isCompleted) {
        // Mettre à jour les .task-container
        const taskItems = document.querySelectorAll(`${selector}[data-id="${eventId}"]`);
        taskItems.forEach(item => {
            // Logique pour .task-container
            const checkEnabledElements = item.querySelectorAll('.check-enabled');
            checkEnabledElements.forEach(element => {
                if (isCompleted) {
                    element.classList.add('completed-task');
                } else {
                    element.classList.remove('completed-task');
                }
            });
            const statusElement = item.querySelector('.status');
            if (statusElement) {
                statusElement.innerHTML = isCompleted
                    ? '<span><i class="fa-solid fa-circle-check"></i></span> Terminé'
                    : '<span><i class="fa-solid fa-circle-notch"></i></span> En cours';
            }
            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.checked = isCompleted;
            }
            // Mettre à jour l'élément .name dans .task-container
            const nameElement = item.querySelector('.name');
            if (nameElement) {
                if (isCompleted) {
                    nameElement.classList.add('completed-task');
                } else {
                    nameElement.classList.remove('completed-task');
                }
            }
        });

        // Mettre à jour les .fc-event
        const calendarItems = document.querySelectorAll(`.fc-event[data-id="${eventId}"]`);
        calendarItems.forEach(item => {
            if (isCompleted) {
                item.classList.add('completed-task');
            } else {
                item.classList.remove('completed-task');
            }
        });

        // Mettre à jour la checkbox du modal si ouvert
        const modalCheckbox = document.querySelector('#modalCompleted');
        if (modalCheckbox && document.getElementById('eventModal').dataset.id === eventId) {
            modalCheckbox.checked = isCompleted;
        }

        // Mettre à jour l'apparence du titre du modal si ouvert
        const modalTitle = document.getElementById('modalTitle');
        if (modalTitle && document.getElementById('eventModal').dataset.id === eventId) {
            if (isCompleted) {
                modalTitle.classList.add('completed-task');
            } else {
                modalTitle.classList.remove('completed-task');
            }
        }

        // Mettre à jour l'événement du calendrier (si applicable)
        if (hasCalendar && calendar) {
            const calendarEvent = calendar.getEventById(eventId);
            if (calendarEvent) {
                calendarEvent.setProp('classNames', isCompleted ? ['completed-event'] : []);
            }
        }
    }

    // Fonction pour rafraîchir les états des tâches
    function refreshTaskStates() {
        const eventIds = Array.from(document.querySelectorAll(selector)).map(item => item.dataset.id);
        eventIds.forEach(eventId => {
            fetch(`/api/assignments/${eventId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur API: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    updateTaskAppearance(eventId, data.isCompleted);
                })
                .catch(error => {
                    console.error('Erreur lors du rafraîchissement de l\'état:', error);
                });
        });
    }

    // Gérer la checkbox du modal
    const modalCheckbox = document.querySelector('#modalCompleted');
    if (modalCheckbox) {
        modalCheckbox.addEventListener('change', function(e) {
            e.stopPropagation();
            const eventId = this.dataset.id;
            const isCompleted = this.checked;

            if (!eventId) {
                console.error('Erreur: eventId non défini pour la checkbox');
                this.checked = !isCompleted;
                return;
            }

            fetch(`/api/assignments/${eventId}/toggle-complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ isCompleted })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur API: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    updateTaskAppearance(eventId, data.isCompleted);
                    refreshTaskStates();
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour:', error);
                    alert('Impossible de mettre à jour l\'état de la tâche.');
                    this.checked = !isCompleted;
                });
        });
    }

    // Initialiser les checkboxes dans .task-container
    document.querySelectorAll(`${selector} input[type="checkbox"]`).forEach(checkbox => {
        checkbox.addEventListener('change', function(e) {
            e.stopPropagation();
            const eventId = this.dataset.id;
            const isCompleted = this.checked;

            if (!eventId) {
                console.error('Erreur: eventId non défini pour la checkbox');
                this.checked = !isCompleted;
                return;
            }

            fetch(`/api/assignments/${eventId}/toggle-complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ isCompleted })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur API: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    updateTaskAppearance(eventId, data.isCompleted);
                    refreshTaskStates();
                })
                .catch(error => {
                    console.error('Erreur lors de la mise à jour:', error);
                    alert('Impossible de mettre à jour l\'état de la tâche.');
                    this.checked = !isCompleted;
                });
        });
    });

    // Rafraîchir les états au chargement
    refreshTaskStates();

    // Exposer refreshTaskStates pour les appels externes
    return {
        refreshTaskStates
    };
}

/**
 * Affiche les détails d'un événement dans le modal.
 * @param {Object} event - Données de l'événement (title, start, description, etc.).
 */
/**
 * Affiche les détails d'un événement dans le modal.
 * @param {Object} event - Données de l'événement (title, start, description, etc.).
 */
/**
 * Affiche les détails d'un événement dans le modal.
 * @param {Object} event - Données de l'événement (title, start, description, etc.).
 */
function showEventDetails(event) {
    console.log('Event passé à showEventDetails:', event);
    console.log('Valeur de courseLocation:', event.course_location);

    const modalTitle = document.getElementById('modalTitle');
    modalTitle.textContent = event.title || 'Sans titre';

    // Appliquer completed-task si la tâche est terminée
    if (event.isCompleted) {
        modalTitle.classList.add('completed-task');
    } else {
        modalTitle.classList.remove('completed-task');
    }

    // Gérer la date avec robustesse
    const dueDate = event.start ? new Date(event.start) : null;
    document.getElementById('modalDate').textContent = dueDate ?
        `${String(dueDate.getUTCDate()).padStart(2, '0')}/${String(dueDate.getUTCMonth() + 1).padStart(2, '0')}/${dueDate.getUTCFullYear()} - ${String(dueDate.getUTCHours()).padStart(2, '0')}:${String(dueDate.getUTCMinutes()).padStart(2, '0')}` :
        'Date inconnue';

    document.getElementById('modalDescription').textContent = event.description || 'Aucune description';
    document.getElementById('modalSubjectCode').textContent = event.subject?.code || 'Non spécifié';
    document.getElementById('modalSubjectName').textContent = event.subject?.name || 'Non spécifié';

    // Gérer l'URL de rendu
    const submissionUrlEl = document.getElementById('modalSubmissionUrl');
    if (event.submissionUrl) {
        submissionUrlEl.innerHTML = `<a href="${event.submissionUrl}" target="_blank">${event.submissionUrl}</a>`;
    } else {
        submissionUrlEl.textContent = 'Aucune URL de rendu';
    }

    // Gérer le mode de rendu
    const submissionTypeEl = document.getElementById('modalSubmissionType');
    const submissionTypeDisplay = event.submissionType ? {
        'moodle': 'Moodle',
        'vps': 'VPS',
        'email': 'Email',
        'other': 'Autre'
    }[event.submissionType.toLowerCase()] || 'Non spécifié' : 'Non spécifié';
    submissionTypeEl.textContent = submissionTypeDisplay;

    // Gérer les précisions supplémentaires
    const submissionOtherContainer = document.getElementById('modalSubmissionOtherContainer');
    if (event.submissionType && event.submissionType.toLowerCase() === 'other' && event.submissionOther) {
        document.getElementById('modalSubmissionOther').textContent = event.submissionOther;
        submissionOtherContainer.style.display = 'block';
    } else {
        submissionOtherContainer.style.display = 'none';
    }

    // Gérer le type d'événement
    document.getElementById('modalType').textContent = event.type ?
        event.type.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ') :
        'Non spécifié';


    // Gérer la checkbox du modal
    document.getElementById('modalCompleted').checked = event.isCompleted || false;
    document.getElementById('eventModal').dataset.id = event.id;

    // Synchroniser dataset.id pour la checkbox du modal
    const modalCheckbox = document.getElementById('modalCompleted');
    if (modalCheckbox) {
        modalCheckbox.dataset.id = event.id;
    }

    // Gérer le lien d'édition
    const editAssignmentLink = document.getElementById('editAssignment');
    if (editAssignmentLink) {
        editAssignmentLink.href = `/assignments/${event.id}/edit`;
    }

    // Afficher le modal
    document.getElementById('eventModal').style.display = 'flex';
}
/**
 * Initialise les écouteurs pour le modal (fermeture et suggestion de modification).
 */
function initModalListeners() {
    const suggestModificationBtn = document.getElementById('suggestModification');
    if (suggestModificationBtn) {
        suggestModificationBtn.addEventListener('click', function() {
            const eventId = document.getElementById('eventModal').dataset.id;
            window.location.href = `/assignment/${eventId}/suggest`;
        });
    }

    const closeModalBtn = document.getElementById('closeModal');
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', function() {
            document.getElementById('eventModal').style.display = 'none';
        });
    }

    // Fermer le modal en cliquant en dehors
    const modalContainer = document.getElementById('eventModal');
    if (modalContainer) {
        modalContainer.addEventListener('click', function(event) {
            if (event.target === modalContainer) {
                modalContainer.style.display = 'none';
            }
        });
    }
}

/**
 * Initialise l'écouteur pour les clics sur les éléments .task-container afin d'ouvrir le modal.
 */
function initTaskContainerListener() {
    document.querySelectorAll('.task-container').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') {
                e.stopPropagation();
                return;
            }
            const assignmentId = this.dataset.id;
            if (!assignmentId) return;
            fetch(`/api/assignments/${assignmentId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur API: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('ID cliqué:', assignmentId);
                    console.log('Data from API:', data);
                    showEventDetails(data);
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des détails:', error);
                    alert('Impossible de charger les détails de l\'événement.');
                });
        });
    });
}

// Exécuter l'initialisation des écouteurs au chargement
document.addEventListener('DOMContentLoaded', function() {
    initModalListeners();
    initTaskContainerListener();
});

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

    // NAV BURGER
    const burger = document.getElementById('burger');
    const navLinks = document.getElementById('navLinks');

    burger.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        burger.classList.toggle('open');
    });


});
// Exposer initTaskManager et showEventDetails dans la portée globale pour les scripts inline
window.initTaskManager = initTaskManager;
window.showEventDetails = showEventDetails;