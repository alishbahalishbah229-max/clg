<?php
?>

<footer class="campus360-footer">

    <div class="campus360-footer-inner">

        <div class="campus360-footer-brand">

            <!-- <div class="campus360-footer-mark">
                C
            </div> -->

            <div class="campus360-footer-brand-text">

                <strong>
                    
                </strong>

                <span>
                    
                </span>

            </div>

        </div>


        <div class="campus360-footer-copy">

            <span>
                © <?= date('Y') ?> Campus360
            </span>

            <span class="footer-separator">
                •
            </span>

            <span>
                College Event Management System
            </span>

        </div>


        <div class="campus360-footer-meta">

            <span>
                Secure
            </span>

            <span class="footer-dot">•</span>

            <span>
                Reliable
            </span>

            <span class="footer-dot">•</span>

            <span>
                Connected
            </span>

        </div>

    </div>

</footer>


<style>

.campus360-footer {

    width: 100%;

    margin-top: 35px;

    padding: 22px 35px;

    background: #071a36;

    border-top:
        1px solid
        rgba(201,154,62,.28);

    color: #ffffff;

}


.campus360-footer-inner {

    width: 100%;

    max-width: 1400px;

    margin: 0 auto;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 30px;

}


/* BRAND */

.campus360-footer-brand {

    display: flex;

    align-items: center;

    gap: 10px;

    flex-shrink: 0;

}


.campus360-footer-mark {

    width: 34px;

    height: 38px;

    display: grid;

    place-items: center;

    background: #06152c;

    border:
        1px solid
        #c99a3e;

    color: #e5c16f;

    font-family: Georgia, serif;

    font-size: 14px;

    font-weight: 700;

    clip-path:
        polygon(
            0 0,
            100% 0,
            100% 78%,
            50% 100%,
            0 78%
        );

}


.campus360-footer-brand-text strong {

    display: block;

    color: #ffffff;

    font-family:
        "Playfair Display",
        serif;

    font-size: 13px;

    line-height: 1;

    letter-spacing: 1.2px;

}


.campus360-footer-brand-text span {

    display: block;

    margin-top: 4px;

    color: #aebbd0;

    font-size: 6px;

    line-height: 1;

    letter-spacing: 1.8px;

}


/* CENTER */

.campus360-footer-copy {

    flex: 1;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    color: #b8c4d3;

    font-size: 8px;

    text-align: center;

}


.footer-separator {

    color: #c99a3e;

}


/* RIGHT */

.campus360-footer-meta {

    display: flex;

    align-items: center;

    gap: 7px;

    flex-shrink: 0;

    color: #8f9db0;

    font-size: 7px;

    white-space: nowrap;

}


.footer-dot {

    color: #c99a3e;

}


/* ==================================================
   ADMIN / ORGANIZER FIX
================================================== */

@media (min-width: 801px) {

    .campus360-footer {

        width: auto;

        margin-left: 0;

    }

}


/* TABLET */

@media (max-width: 900px) {

    .campus360-footer {

        padding:
            22px 25px;

    }


    .campus360-footer-inner {

        gap: 18px;

    }

}


/* MOBILE */

@media (max-width: 650px) {

    .campus360-footer {

        padding:
            20px 17px;

    }


    .campus360-footer-inner {

        flex-direction: column;

        justify-content: center;

        text-align: center;

        gap: 13px;

    }


    .campus360-footer-brand {

        justify-content: center;

    }


    .campus360-footer-copy {

        flex-wrap: wrap;

        line-height: 1.5;

    }


    .campus360-footer-meta {

        justify-content: center;

        white-space: normal;

    }

}

</style>