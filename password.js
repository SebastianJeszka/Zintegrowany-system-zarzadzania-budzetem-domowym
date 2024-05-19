// Dodanie nasłuchiwacza do całego dokumentu, który zostanie uruchomiony po załadowaniu strony
window.addEventListener("DOMContentLoaded", (event) => {
  // Pobranie elementów UI odpowiedzialnych za przełączanie widoczności hasła
  const togglePassword = document.getElementById("togglePassword");
  const passwordInput = document.getElementById("password");

  // Dodanie nasłuchiwacza kliknięcia, który zmienia typ inputu hasła między tekstowym a hasłem
  togglePassword.addEventListener("click", function () {
    // Sprawdzenie obecnego typu inputu i zmiana go
    const type =
      passwordInput.getAttribute("type") === "password" ? "text" : "password";
    passwordInput.setAttribute("type", type);
    // Zmiana ikony w zależności od typu
    this.textContent = type === "password" ? "👁️" : "👁️‍🗨️";

    // Pobranie elementów formularza związanych z opcjami rodziny
    const familyOptionElements = document.getElementsByName("familyOption");
    const familyNameContainer = document.getElementById("familyNameContainer");
    const familyCodeInput = document.getElementById("familyCodeInput");
    const familyCodeError = document.getElementById("familyCodeError");

    // Elementy związane z pseudonimem
    const nicknameInput = document.querySelector('input[name="nickname"]');
    const nicknameError = document.getElementById("nicknameError");

    // Inicjalizacja zmiennych dla kodu i nazwy rodziny
    let familyCode, familyName;

    // Pobranie inputów dla kodu i nazwy rodziny
    if (familyCodeInput) {
      familyCode = familyCodeInput.querySelector("input");
    }

    if (familyNameContainer) {
      familyName = familyNameContainer.querySelector("input");
    }

    // Funkcja przełączająca widoczność pól w zależności od wybranej opcji rodziny
    const toggleFamilyInputDisplay = (optionValue) => {
      if (optionValue === "new") {
        // Wyświetlenie pola nazwy nowej rodziny i ukrycie pola kodu dołączenia
        familyNameContainer.style.display = "block";
        familyCodeInput.style.display = "none";
        // Ustawienie atrybutów required odpowiednio
        familyCode.required = false;
        familyName.required = true;
      } else if (optionValue === "join") {
        // Ukrycie pola nazwy nowej rodziny i wyświetlenie pola kodu dołączenia
        familyNameContainer.style.display = "none";
        familyCodeInput.style.display = "block";
        familyCode.required = true;
        familyName.required = false;
      }
    };

    // Ustawienie początkowego stanu formularza na podstawie wybranej opcji rodziny
    const initialOption = Array.from(familyOptionElements).find(
      (radio) => radio.checked
    )?.value;
    toggleFamilyInputDisplay(initialOption);

    // Dodanie obsługi zdarzeń dla zmiany opcji rodziny
    familyOptionElements.forEach((option) => {
      option.addEventListener("change", function () {
        toggleFamilyInputDisplay(this.value);
      });
    });

    // Dodanie walidacji pseudonimu użytkownika poprzez asynchroniczne zapytanie do serwera
    nicknameInput.addEventListener("keyup", function () {
      const nickname = this.value;
      fetch("check_nickname.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "nickname=" + encodeURIComponent(nickname),
      })
        .then((response) => response.text())
        .then((data) => {
          // Wyświetlenie błędu, jeśli pseudonim jest już zajęty
          nicknameError.textContent =
            data === "taken"
              ? " "
              : "";
          nicknameError.style.color = data === "taken" ? "red" : "inherit";
        })
        .catch((error) => {
          console.error("Error:", error);
        });
    });

    // Dodanie walidacji kodu rodziny poprzez asynchroniczne zapytanie do serwera
    familyCode.addEventListener("keyup", function () {
      if (this.value.length === 5) {
        fetch("check_family_code.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: "familyCode=" + encodeURIComponent(this.value),
        })
          .then((response) => response.json())
          .then((response) => {
            // Wyświetlenie błędu, jeśli kod rodziny nie został znaleziony
            if (response.status === "not_found") {
              familyCodeError.textContent = response.message;
              familyCodeError.style.display = "block";
            } else {
              familyCodeError.style.display = "none";
            }
          })
          .catch((error) => {
            console.error("Error:", error);
          });
      } else {
        familyCodeError.style.display = "none";
      }
    });
  });
});
