// Główny nasłuchiwacz zdarzeń, który czeka na załadowanie całego DOM.
document.addEventListener("DOMContentLoaded", function () {
  // Pobranie modalu do dodawania kategorii.
  var modal = document.getElementById("addCategoryModal");

  // Pobranie przycisku otwierającego modal.
  var btn = document.getElementById("addCategoryBtn");

  // Pobranie elementu <span>, który służy do zamknięcia modalu.
  var span = document.getElementsByClassName("close")[0];

  // Otwieranie modalu po kliknięciu przycisku.
  btn.onclick = function () {
    modal.style.display = "block";
  };

  // Zamknięcie modalu po kliknięciu na <span> (x).
  span.onclick = function () {
    modal.style.display = "none";
  };

  // Zamknięcie modalu po kliknięciu poza jego obszarem.
  window.onclick = function (event) {
    if (event.target == modal) {
      modal.style.display = "none";
    }
  };

  // Przełączanie między kategoriami wydatków a dochodów
  const wydatkiBtn = document.getElementById("wydatkiBtn");
  const dochodyBtn = document.getElementById("dochodyBtn");
  const kategorieWydatkow = document.getElementById("kategorieWydatkow");
  const kategorieDochodow = document.getElementById("kategorieDochodow");

  let currentCategoryType = "wydatki"; // Domyślnie ustawia kategorie na 'wydatki'.
});

// Dodatkowy nasłuchiwacz dla rozsuwania sidebara.
document.addEventListener("DOMContentLoaded", function () {
  var sidebar = document.querySelector(".sidebar");
  var toggle = document.getElementById("toggleSidebar");

  toggle.addEventListener("click", function () {
    sidebar.classList.toggle("expanded");
  });
});
// Domyślne ustawienia dla wyświetlania kategorii wydatków i ukrycia dochodów.
kategorieWydatkow.style.display = "block";
kategorieDochodow.style.display = "none";
currentCategoryType = "wydatki"; // Ustawienie domyślnej kategorii na 'wydatki'

// Nasłuchiwanie kliknięć na przyciskach do przełączania kategorii.
wydatkiBtn.addEventListener("click", function () {
  kategorieWydatkow.style.display = "block";
  kategorieDochodow.style.display = "none";
  currentCategoryType = "wydatki"; // aktualizacja aktualnego typu kategorii
});

dochodyBtn.addEventListener("click", function () {
  kategorieWydatkow.style.display = "none";
  kategorieDochodow.style.display = "block";
  currentCategoryType = "dochody"; // aktualizacja aktualnego typu kategorii
});
// Obsługa usuwania kategorii po potwierdzeniu w modalu.
document.addEventListener("DOMContentLoaded", (event) => {
  var currentCategoryElement = null;

  // Dodanie nasłuchiwaczy do każdego przycisku "usuń kategorię".
  document.querySelectorAll(".delete-category").forEach((button) => {
    button.addEventListener("click", function () {
      currentCategoryElement = this.parentElement; // Przechowywanie elementu listy
      const categoryName = this.dataset.category; // Pobranie nazwy kategorii
      document.getElementById("categoryToDelete").textContent = categoryName;
      document.getElementById("deleteConfirmationModal").style.display =
        "block";
    });
  });

  // Usuwanie kategorii po potwierdzeniu.
  document
    .getElementById("confirmDelete")
    .addEventListener("click", function () {
      const categoryName =
        document.getElementById("categoryToDelete").textContent;
      const data = new FormData();
      data.append("categoryName", categoryName);
      data.append("action", "delete");
      data.append("categoryType", currentCategoryType); // Dodanie typu kategorii do danych formularza

      // Wykonanie zapytania do serwera w celu usunięcia kategorii.
      fetch("delete_category.php", {
        method: "POST",
        body: data,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Usunięcie kategorii z widoku bez przeładowania strony
            currentCategoryElement.remove();
          } else {
            // Wyświetlenie błędu, jeśli operacja się nie powiodła
            alert(data.message);
          }
          // Zamknięcie modalu niezależnie od wyniku
          document.getElementById("deleteConfirmationModal").style.display =
            "none";
        })
        .catch((error) => {
          console.error("Error:", error);
        });
    });

  // Anulowanie usuwania kategorii i zamknięcie modalu
  document
    .getElementById("cancelDelete")
    .addEventListener("click", function () {
      document.getElementById("deleteConfirmationModal").style.display = "none";
    });
});
