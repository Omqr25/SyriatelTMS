import { ArrowRight, ArrowLeft, GraduationCap, Network, Rocket, UsersRound, Target } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import styles from './Home.module.css';

const icons = [Network, GraduationCap, Target, UsersRound];
export default function Home(){
  const {t,i18n}=useTranslation(); const Arrow=i18n.dir()==='rtl'?ArrowLeft:ArrowRight;
  const benefits=['real','mentor','skills','career'];
  return <>
    <section className={styles.hero}>
      <div className={styles.heroGlow}/><div className={styles.grid}/>
      <div className={styles.heroInner}>
        <div className={styles.copy}>
          <span className={styles.eyebrow}><span/> {t('hero.eyebrow')}</span>
          <h1>{t('hero.title1')}<br/>{t('hero.title2')}<br/><strong>{t('hero.title3')}</strong></h1>
          <p>{t('hero.description')}</p>
          <div className={styles.heroActions}><Link to="/apply" className={styles.primary}>{t('hero.cta')} <Arrow size={19}/></Link><a href="#benefits" className={styles.secondary}>{t('hero.explore')}</a></div>
          <div className={styles.stats}><div><b>01</b><span>Practical<br/>Experience</span></div><div><b>02</b><span>Expert<br/>Mentorship</span></div><div><b>03</b><span>Career<br/>Growth</span></div></div>
        </div>
        <div className={styles.visual} aria-hidden="true">
          <div className={styles.sun}/><div className={styles.tower}><i/><i/><i/><i/></div>
          <div className={styles.person}><div className={styles.head}/><div className={styles.body}/><div className={styles.backpack}/></div>
          <div className={styles.city}><i/><i/><i/><i/><i/><i/><i/></div>
          <div className={styles.arc}/>
          <div className={styles.visualBadge}><Rocket size={18}/><span>Learn · Build · Grow</span></div>
        </div>
      </div>
    </section>
    <section id="benefits" className={styles.benefits}><div className={styles.sectionHead}><div><span className={styles.kicker}>SYRIATEL</span><h2>{t('benefits.title')}</h2></div><p>{t('benefits.subtitle')}</p></div><div className={styles.cards}>{benefits.map((key,i)=>{const Icon=icons[i];return <article className={styles.card} key={key}><div className={styles.icon}><Icon size={24}/></div><h3>{t(`benefits.${key}.title`)}</h3><p>{t(`benefits.${key}.text`)}</p></article>})}</div></section>
    <section className={styles.quote}><div><span>“</span><p>{t('quote')}</p></div><Link to="/apply">{t('nav.apply')} <Arrow size={17}/></Link></section>
  </>;
}
