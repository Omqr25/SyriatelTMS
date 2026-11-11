import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import {
  ArrowLeft,
  ArrowRight,
  ChevronDown,
  Search,
} from "lucide-react";
import { useTranslation } from "react-i18next";

import styles from "./FAQPage.module.css";

export default function FAQ() {
  const { t, i18n } = useTranslation();

  const [openIndex, setOpenIndex] = useState(null);
  const [search, setSearch] = useState("");

  const isRTL = i18n.dir() === "rtl";

  const BackArrow = isRTL ? ArrowRight : ArrowLeft;

  const questions = t("faq.questions", {
    returnObjects: true,
  });

  const filteredQuestions = useMemo(() => {
    if (!search.trim()) {
      return questions;
    }

    const query = search.toLowerCase();

    return questions.filter(
      (item) =>
        item.question.toLowerCase().includes(query) ||
        item.answer.toLowerCase().includes(query)
    );
  }, [questions, search]);

  const handleToggle = (index) => {
    setOpenIndex((previousIndex) =>
      previousIndex === index ? null : index
    );
  };

  return (
    <main className={styles.page}>
      <div className={styles.container}>

        {/* Back */}
        <Link to="/" className={styles.back}>
          <BackArrow size={17} />
          <span>{t("faq.back")}</span>
        </Link>

        {/* Heading */}
        <header className={styles.heading}>
          <span>{t("brand.program")}</span>

          <h1>
            {t("faq.title")}{" "}
            <strong>{t("faq.titleAccent")}</strong>
          </h1>

          <p>{t("faq.intro")}</p>
        </header>

        {/* Search */}
        <div className={styles.searchWrapper}>
          <Search size={19} />

          <input
            type="search"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setOpenIndex(null);
            }}
            placeholder={t("faq.searchPlaceholder")}
            aria-label={t("faq.searchPlaceholder")}
          />
        </div>

        {/* FAQ */}
        <section className={styles.faqList}>
          {filteredQuestions.length > 0 ? (
            filteredQuestions.map((item, index) => {
              const isOpen = openIndex === index;

              return (
                <article
                  key={item.question}
                  className={`${styles.faqItem} ${
                    isOpen ? styles.open : ""
                  }`}
                >
                  <button
                    type="button"
                    className={styles.question}
                    onClick={() => handleToggle(index)}
                    aria-expanded={isOpen}
                  >
                    <span>{item.question}</span>

                    <ChevronDown
                      size={20}
                      className={styles.chevron}
                    />
                  </button>

                  {isOpen && (
                    <div className={styles.answer}>
                      <p>{item.answer}</p>
                    </div>
                  )}
                </article>
              );
            })
          ) : (
            <div className={styles.empty}>
              <Search size={30} />

              <h3>{t("faq.noResults")}</h3>

              <p>{t("faq.noResultsHint")}</p>
            </div>
          )}
        </section>

        {/* Bottom help */}
        <section className={styles.help}>
          <h2>{t("faq.help.title")}</h2>

          <p>{t("faq.help.message")}</p>

          <Link to="/" className={styles.homeButton}>
            {t("faq.help.button")}
          </Link>
        </section>
      </div>
    </main>
  );
}