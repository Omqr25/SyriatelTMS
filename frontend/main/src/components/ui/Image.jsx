import style from  "./Image.module.css"
export default function Image({imageSrc="https://tse2.mm.bing.net/th/id/OIP.UI40OfyI0EFuXBAU-PClYQHaE8?r=0&rs=1&pid=ImgDetMain&o=7&rm=3"}){
    return(
    <>
        <div className={style["profile-container"]}>
            <img src={imageSrc} alt="Profile" className={style["profile-image"]} />
        </div>
    </>)
}