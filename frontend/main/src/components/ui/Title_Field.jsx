import style from "./Title_Field.module.css"
export default function TitleField({title,subTitle}){
    return (
    <>
    <div className={style["Title-Field"]}>
        <h1 className={style["Title-Style"]}>{title}</h1>
        <h5 className={style["Sub-Title-Style"]}>{subTitle}</h5>
    </div>
    </>);
}